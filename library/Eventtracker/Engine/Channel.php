<?php

namespace Icinga\Module\Eventtracker\Engine;

use Icinga\Module\Eventtracker\DbFactory;
use Icinga\Module\Eventtracker\Event;
use Icinga\Module\Eventtracker\EventReceiver;
use Icinga\Module\Eventtracker\Modifier\ModifierChain;
use Icinga\Module\Eventtracker\Modifier\Settings;
use Icinga\Module\Eventtracker\SenderInventory;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
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

    public function __construct(Settings $settings, UuidInterface $uuid, $name)
    {
        $this->uuid = $uuid;
        $this->name = $name;
        $this->logger = new NullLogger();
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

    public function addInput(Input $input)
    {
        $this->logger->info("Wiring " . $input->getName() . ' to ' . $this->name);
        $input->on('event', function (\stdClass $event) {
            $this->process($event);
        });
    }

    public function process(\stdClass $object)
    {
        $this->rules->process($object);
        $db = DbFactory::db();
        $senders = new SenderInventory($db);
        $receiver = new EventReceiver($db);
        $senderId = $senders->getSenderId('debugging', 'Debugging');

        $enforced = ModifierChain::fromSerialization([
            ['object_name', 'ShortenString', (object) ['max_length' => 128]],
            ['object_class', 'ShortenString', (object) ['max_length' => 128]],
            ['object_class', 'ClassInventoryLookup'],
            ['priority', 'FallbackValue', (object) ['value' => 'normal']],
            ['sender_id', 'SetValue', (object) ['value' => $senderId]],
        ]);
        $enforced->process($object);
        $object->sender_event_id = Uuid::uuid4()->toString();

        $event = new Event();
        $event->setProperties((array) $object);
        $issue = $receiver->processEvent($event);
        if ($issue) {
            $this->logger->info("Issue " . $issue->getNiceUuid());
        } else {
            $this->logger->debug("No Issue");
        }
    }
}
