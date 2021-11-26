<?php

namespace Icinga\Module\Eventtracker\Engine;

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

class Channel implements LoggerAwareInterface
{
    use LoggerAwareTrait;
    use SettingsProperty;

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
        $input->on('event', function (\stdClass $event) use ($inputUuid, $ignoreUnknown) {
            $this->process($inputUuid, $event, $ignoreUnknown);
        });
    }

    public function process(UuidInterface $inputUuid, \stdClass $object, $ignoreUnknown = false)
    {
        $this->rules->process($object);
        $db = DbFactory::db();
        $receiver = new EventReceiver($db);

        $enforced = ModifierChain::fromSerialization([
            ['object_name', 'ShortenString', (object) ['max_length' => 128]],
            ['object_class', 'ShortenString', (object) ['max_length' => 128]],
            ['object_class', 'ClassInventoryLookup'],
            ['priority', 'FallbackValue', (object) ['value' => 'normal']],
            ['input_uuid', 'SetValue', (object) ['value' => $inputUuid->toString()]],
            ['sender_id', 'SetValue', (object) ['value' => '99999']],
        ]);

        $enforced->process($object);
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
        } else {
            $this->logger->debug("No Issue");
        }
    }
}
