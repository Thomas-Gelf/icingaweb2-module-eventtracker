<?php

namespace Icinga\Module\Eventtracker\Scom;

use Icinga\Application\Config;
use Icinga\Module\Eventtracker\ConfigHelper;
use Icinga\Module\Eventtracker\Event;
use Icinga\Module\Eventtracker\ObjectClassInventory;

class ScomEventFactory
{
    protected $senderId;

    protected $classInventory;

    public function __construct($senderId, ObjectClassInventory $classInventory)
    {
        $this->senderId = $senderId;
        $this->classInventory = $classInventory;
    }

    public function fromPlainObject($obj)
    {
        $event = Event::create([
            'host_name'       => $obj->entity_name,
            'object_name'     => \substr($obj->alert_name, 0, 128),
            'object_class'    => $this->classInventory->requireClass(\substr($obj->entity_base_type, 0, 128)),
            'severity'        => $obj->alert_severity,
            'priority'        => $obj->alert_priority,
            'message'         => $obj->description ?: '-',
            'sender_event_id' => $obj->alert_id,
            'sender_id'       => $this->senderId,
        ]);
        $attributes = [];
        foreach (Config::module('eventtracker')->getSection('scom_attributes') as $name => $value) {
            $attributes[$name] = ConfigHelper::fillPlaceholders($value, $obj);
        }
        $event->set('attributes', $attributes);

        return $event;
    }
}
