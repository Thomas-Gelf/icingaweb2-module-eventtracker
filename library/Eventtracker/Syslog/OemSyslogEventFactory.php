<?php

namespace Icinga\Module\Eventtracker\Syslog;

use Icinga\Application\Config;
use Icinga\Module\Eventtracker\ConfigHelper;
use Icinga\Module\Eventtracker\Event;
use Icinga\Module\Eventtracker\Modifier\ClassInventoryLookup;
use Icinga\Module\Eventtracker\Modifier\ConvertUtcTimeWithMsToTimestamp;
use Icinga\Module\Eventtracker\Modifier\ModifierChain;
use Icinga\Module\Eventtracker\Modifier\NumericSyslogSeverityMapper;
use Icinga\Module\Eventtracker\Modifier\PropertyMapper;
use Icinga\Module\Eventtracker\Modifier\Settings;
use Icinga\Module\Eventtracker\Modifier\ShortenString;
use Icinga\Module\Eventtracker\Priority;

class OemSyslogEventFactory
{
    protected $senderId;

    public function __construct($senderId)
    {
        $this->senderId = $senderId;
    }

    protected function getPropertyMapper()
    {
        return new PropertyMapper([
            'hostname'        => 'host_name', // there is also oem_host_name
            'oem_target_name' => 'object_name', // 'dbms12ab_site1.example.com_abcde'
            'oem_target_type' => 'object_class', // 'oracle_pdb'
            // severity: numeric -> ?!
            'severity'        => 'severity',
            // msg: The pluggable database DBMS12AB_SITE1.EXAMPLE.COM_ABCDE is down. OEMIncidentID: 123456
            'msg'             => 'message',
            'oem_incident_id' => 'sender_event_id', // 123456
            'oem_issue_type'  => 'attributes.oem_issue_type',
            // 'oem_incident_ack_by_owner' "no"
            // 'timestamp'       => 'source_timestamp', // 2021-06-02T13:24:30.276Z
            // id => != oem_incident_id
            // oem_clear => false/true
            // state => ok/nok?
            // component => DBMS12AB_SITE1.EXAMPLE.COM_ABCDE
            // oem_incident_ack_by_owner => no
            // oem_incident_status => new
            // oem_issue_type => incident
        ]);
    }

    protected function getDefaultProperties()
    {
        return [
            'priority'  => Priority::NORMAL,
        ];
    }

    protected function getEnforcedProperties()
    {
        return [
            'sender_id' => $this->senderId,
        ];
    }

    protected function getEnforcedModifiers()
    {
        $config = [
            ['object_name', 'shortenString', (object) ['max_length' => 128]]
        ];
        $max128 = new ShortenString(Settings::fromSerialization((object) [
            'max_length' => 128
        ]));
        return new ModifierChain([
            ['object_name', $max128],
            ['object_class', $max128],
            ['object_class', new ClassInventoryLookup(new Settings())],
            // TODO: not yet, disabled above:
            ['source_timestamp', new ConvertUtcTimeWithMsToTimestamp(new Settings())],
        ]);
    }
    protected function getModifiers()
    {
        return new ModifierChain([
            ['severity', new NumericSyslogSeverityMapper(new Settings())],
        ]);
    }

    public function fromPlainObject($obj)
    {
        $properties = (object) (
            $this->getEnforcedProperties()
            + $this->getPropertyMapper()->mapArray((array) $obj)
            + $this->getDefaultProperties()
        );

        $this->getModifiers()->process($properties);
        $this->getEnforcedModifiers()->process($properties);

        $event = new Event();
        $event->setProperties((array) $properties);

        // TODO: remove these!
        $attributes = [];
        foreach (Config::module('eventtracker')->getSection('oem_syslog_attributes') as $name => $value) {
            $attributes[$name] = ConfigHelper::fillPlaceholders($value, $obj);
        }
        $event->set('attributes', $attributes);

        return $event;
    }
}
