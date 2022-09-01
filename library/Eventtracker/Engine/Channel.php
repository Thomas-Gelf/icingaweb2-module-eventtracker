<?php

namespace Icinga\Module\Eventtracker\Engine;

use Evenement\EventEmitterTrait;
use Icinga\Module\Eventtracker\DbFactory;
use Icinga\Module\Eventtracker\Event;
use Icinga\Module\Eventtracker\EventReceiver;
use Icinga\Module\Eventtracker\Modifier\ModifierChain;
use Icinga\Module\Eventtracker\Modifier\Settings;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use stdClass;

class Channel implements LoggerAwareInterface
{
    use EventEmitterTrait;
    use LoggerAwareTrait;
    use SettingsProperty;

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

    public function __construct(Settings $settings, UuidInterface $uuid, $name, LoggerInterface $logger = null)
    {
        $this->uuid = $uuid;
        $this->name = $name;
        if ($logger === null) {
            $this->logger = new NullLogger();
        } else {
            $this->logger = $logger;
        }
        $this->applySettings($settings);
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
            $this->process($inputUuid, $event, $ignoreUnknown);
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

        $event = new Event();
        if (isset($object->input_uuid)) {
            $object->input_uuid = Uuid::fromString($object->input_uuid)->getBytes();
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
        $issue = $receiver->processEvent($event);
        if ($issue) {
            $this->logger->info("Issue " . $issue->getNiceUuid());
            if ($issue->hasBeenCreatedNow()) {
                $this->emit(static::ON_ISSUE, [$issue]);
            }
        } else {
            $this->logger->debug("No Issue");
        }
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
