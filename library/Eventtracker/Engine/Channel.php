<?php

namespace Icinga\Module\Eventtracker\Engine;

use Evenement\EventEmitterTrait;
use gipfl\Json\JsonString;
use gipfl\ZfDb\Adapter\Adapter;
use Icinga\Module\Eventtracker\ConfigHelper;
use Icinga\Module\Eventtracker\DbFactory;
use Icinga\Module\Eventtracker\Engine\Bucket\BucketInterface;
use Icinga\Module\Eventtracker\Engine\Downtime\DowntimeRunner;
use Icinga\Module\Eventtracker\Engine\Downtime\UuidObjectHelper;
use Icinga\Module\Eventtracker\Event;
use Icinga\Module\Eventtracker\EventReceiver;
use Icinga\Module\Eventtracker\Issue;
use Icinga\Module\Eventtracker\Modifier\ModifierChain;
use Icinga\Module\Eventtracker\Modifier\Settings;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use React\EventLoop\LoopInterface;
use stdClass;

class Channel implements LoggerAwareInterface
{
    use EventEmitterTrait;
    use LoggerAwareTrait;
    use UuidObjectHelper;
    use SettingsProperty;

    protected $defaultProperties = [
        'uuid'                 => null,
        'label'                => null,
        'rules'                => null,
        'input_implementation' => null,
        'input_uuids'          => null,
        'bucket_uuid'          => null,
        'bucket_name'          => null,
    ];

    public const ON_ISSUE = 'issue';

    /** @var UuidInterface */
    protected $uuid;

    /** @var string */
    protected $name;

    /** @var ModifierChain */
    protected $rules;

    /** @var UuidInterface[] */
    protected $uuidFilter = [];

    /** @var string */
    protected $implementationFilter;

    /** @var NullLogger */
    protected $logger;

    /** @var bool */
    protected $daemonized = false;

    /** @var ?LoopInterface */
    protected $loop = null;

    /** @var array<string, BucketInterface> indexed by name */
    protected $buckets = [];

    /** @var ?BucketInterface */
    protected $bucket = null;

    /** @var ?string */
    protected $bucketName = null;

    /** @var ?DowntimeRunner */
    protected $downtimeRunner = null;

    public function __construct(
        Settings $settings,
        UuidInterface $uuid,
        $name,
        ?UuidInterface $bucketUuid,
        ?string $bucketName,
        array $buckets = [],
        ?LoggerInterface $logger = null,
        ?LoopInterface $loop = null
    ) {
        $this->uuid = $uuid;
        $this->name = $name;
        if ($logger === null) {
            $this->logger = new NullLogger();
        } else {
            $this->logger = $logger;
        }
        $this->loop = $loop;
        $this->buckets = [];
        foreach ($buckets as $bucket) {
            $this->buckets[$bucket->getName()] = $bucket;
        }
        if ($bucketUuid) {
            $binaryBucketUuid = $bucketUuid->getBytes();
            foreach ($this->buckets as $bucket) {
                if ($binaryBucketUuid === $bucket->getUuid()->getBytes()) {
                    $this->bucket = $bucket;
                }
            }
        }
        $this->bucketName = $bucketName;
        $this->applySettings($settings);
    }

    public function setDowntimeRunner(DowntimeRunner $downtimeRunner)
    {
        $this->downtimeRunner = $downtimeRunner;
    }

    public function isDaemonized(): bool
    {
        return $this->daemonized;
    }

    public function setDaemonized(bool $daemonized = true): self
    {
        $this->daemonized = $daemonized;

        return $this;
    }

    /**
     * TODO: Compare with former settings, re-wire when changed
     *
     * @param Settings $settings
     */
    protected function applySettings(Settings $settings)
    {
        $this->setSettings($settings);
        $this->rules = ModifierChain::fromSerialization($settings->requireArray('rules'));

        if ($uuids = $settings->getArray('inputs')) {
            foreach ($uuids as $uuid) {
                $this->uuidFilter[] = Uuid::fromString($uuid);
            }
        }
        $this->implementationFilter = $settings->get('implementation');
    }

    public function wantsInput(Input $input)
    {
        if (! empty($this->uuidFilter)) {
            foreach ($this->uuidFilter as $uuid) {
                if ($input->getUuid()->equals($uuid)) {
                    return true;
                }
            }

            return false;
        }

        if ($this->implementationFilter === null) {
            return true;
        } else {
            return $input instanceof $this->implementationFilter;
        }
    }

    public function addInput(Input $input, $ignoreUnknown = false)
    {
        $this->logger->info("Wiring " . $input->getName() . ' to ' . $this->name);
        $inputUuid = $input->getUuid();
        $input->on(InputRunner::ON_EVENT, function (stdClass $event) use ($inputUuid, $ignoreUnknown) {
            try {
                $this->process($inputUuid, $event, $ignoreUnknown);
            } catch (\Throwable $e) {
                $this->logger->error($e->getMessage());
            }
        });
    }

    public function process(UuidInterface $inputUuid, stdClass $object, $ignoreUnknown = false)
    {
        $this->rules->process($object);
        $db = DbFactory::db();
        $receiver = new EventReceiver($db, ! $this->isDaemonized());
        $this->getEnforcedModifiers($inputUuid)->process($object);
        // print_r($object);
        // $object->sender_event_id = Uuid::uuid4()->toString();

        $event = Event::create();
        if ($this->loop !== null) {
            // Temporarily disabled. Also: this is after modifiers, too late
            // $this->storeRawEvent($inputUuid, $db, $object, $event->get('uuid'));
        }
        if (isset($object->input_uuid)) {
            $object->input_uuid = Uuid::fromString($object->input_uuid)->getBytes(); // Why?
        }
        if ($ignoreUnknown) {
            $knownProperties = $event->getProperties();
            foreach ((array) $object as $property => $value) {
                if (array_key_exists($property, $knownProperties)) {
                    $event->set($property, $value);
                }
            }
        } else {
            $event->setProperties((array) $object);
        }
        try {
            if ($bucket = $this->getOptionalBucketForEvent($object)) {
                $event = $bucket->processEvent($event);
                if ($event === null) {
                    return;
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());
        }
        if ($this->loop === null) {
            $issue = $receiver->processEvent($event);
        } else {
            $issue = $this->storeProcessedEvent($db, $event);
        }
        if ($issue) {
            $this->logger->info("Issue " . $issue->getNiceUuid());
            if ($issue->hasBeenCreatedNow()) {
                $this->emit(static::ON_ISSUE, [$issue]);
            }
        } else {
            // $this->logger->debug("No Issue");
        }
    }

    protected function getOptionalBucketForEvent(stdClass $event): ?BucketInterface
    {
        if ($this->bucket) {
            return $this->bucket;
        }

        if ($this->bucketName) {
            try {
                $name = ConfigHelper::fillPlaceholders($this->bucketName, $event);
                return $this->buckets[$name] ?? null;
            } catch (\Throwable $e) {
                $this->logger->error(sprintf(
                    'Failed to extract %s: %s (%s:%d)',
                    $this->bucketName,
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine()
                ));
            }
        }

        return null;
    }

    protected function storeRawEvent(UuidInterface $inputUuid, Adapter $db, stdClass $event, string $binaryUuid)
    {
        $db->insert('raw_event', [
            'input_uuid'        => $inputUuid->getBytes(),
            'event_uuid'        => $binaryUuid, // TODO: place on event
            'ts_received'       => (int) microtime(true) * 1000,
            // 'failed', 'ignored', 'issue_created', 'issue_refreshed', 'issue_acknowledged', 'issue_closed'
            'processing_result' => 'received', // impossible to tell?!
            'error_message'     => null,
            'raw_input'         => JsonString::encode($event),
            'input_format'      => 'json',
        ]);
    }

    protected function storeProcessedEvent(Adapter $db, Event $event): ?Issue
    {
        $issue = Issue::loadIfEventExists($event, $db);
        if ($event->hasBeenCleared()) {
            if ($issue) {
                // $this->counters->increment(self::CNT_RECOVERED);
                $issue->recover($event, $db);
                // TODO: Tell DowntimeRunner?
            } else {
                // $this->counters->increment(self::CNT_IGNORED);
                return null;
            }
        } elseif ($event->isProblem()) {
            if ($issue) {
                // $this->counters->increment(self::CNT_REFRESHED);
                $issue->setPropertiesFromEvent($event);
            } else {
                $issue = Issue::createFromEvent($event);
                // $this->counters->increment(self::CNT_NEW);
            }
            if ($this->downtimeRunner === null) {
                $this->logger->notice('DowntimeRunner is missing in channel');
            } else {
                if ($this->downtimeRunner->issueShouldBeInDowntime($issue)) {
                    $issue->set('status', 'in_downtime');
                }
            }
            $issue->storeToDb($db);
        } elseif ($issue) {
            // $this->counters->increment(self::CNT_RECOVERED);
            $issue->recover($event, $db);

            return null;
        } else {
            // $this->counters->increment(self::CNT_IGNORED);
            return null;
        }

        return $issue;
    }

    protected function getEnforcedModifiers(UuidInterface $inputUuid): ModifierChain
    {
        return ModifierChain::fromSerialization([
            ['object_name', 'ShortenString', (object) ['max_length' => 128]],
            ['object_class', 'ShortenString', (object) ['max_length' => 128]],
            ['object_class', 'ClassInventoryLookup'],
            ['priority', 'FallbackValue', (object) ['value' => 'normal']],
            ['input_uuid', 'SetValue', (object) ['value' => $inputUuid->toString()]],
            ['sender_id', 'SetValue', (object) ['value' => '99999']],
        ]);
    }
}
