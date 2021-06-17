<?php

namespace Icinga\Module\Eventtracker\Syslog;

use Icinga\Module\Eventtracker\Event;
use Icinga\Module\Eventtracker\Modifier\ClassInventoryLookup;
use Icinga\Module\Eventtracker\Modifier\ConvertUtcTimeWithMsToTimestamp;
use Icinga\Module\Eventtracker\Modifier\ModifierChain;
use Icinga\Module\Eventtracker\Modifier\MoveProperty;
use Icinga\Module\Eventtracker\Modifier\Settings;
use Icinga\Module\Eventtracker\Modifier\ShortenString;
use Icinga\Module\Eventtracker\Modifier\SimpleNameValueParser;
use Icinga\Module\Eventtracker\Modifier\UnsetProperty;
use Icinga\Module\Eventtracker\Priority;

class SyslogEventFactory
{
    protected $senderId;

    public function __construct($senderId)
    {
        $this->senderId = $senderId;
    }

    protected function getEnforcedModifiers()
    {
        // TODO: Config -> not yet - differs from reality
        return ModifierChain::fromSerialization([
            ['object_name', 'ShortenString', (object) ['max_length' => 128]],
            ['object_class', 'ShortenString', (object) ['max_length' => 128]],
            ['object_class', 'ClassInventoryLookup'],
        ]);
            // End of Config
        // Code Variant:
        $max128 = new ShortenString(Settings::fromSerialization((object) [
            'max_length' => 128
        ]));
        return new ModifierChain([
            ['object_name', $max128],
            ['object_class', $max128],
            ['object_class', new ClassInventoryLookup(new Settings())],
        ]);
    }

    protected function getModifiers()
    {
        return ModifierChain::fromSerialization([
            ['message', 'MoveProperty', (object) ['target_property' => 'attributes']],
            ['attributes', 'SimpleNameValueParser'],
            ['attributes.msg', 'MoveProperty', (object) ['target_property' => 'message']],
            ['attributes.oem_incident_id', 'MoveProperty', (object) ['target_property' => 'sender_event_id']],
            ['attributes.id', 'UnsetProperty'],
            ['attributes.timestamp', 'ConvertUtcTimeWithMsToTimestamp'],
            ['attributes.timestamp', 'UnsetProperty'], // for now, otherwise we have diffs
            ['attributes.state', 'UnsetProperty'],
            ['attributes.oem_clear', 'MoveProperty', (object) ['target_property' => 'clear']],
            ['clear', 'MakeBoolean'],

            // TODO: switch order, test
            ['attributes.oem_incident_ack_by_owner', 'MoveProperty', (object) ['target_property' => 'acknowledge']],
            ['acknowledge', 'MakeBoolean'],
            ['attributes.hostname', 'MoveProperty', (object) ['target_property' => 'host_name']],
            ['attributes.oem_host_name', 'MoveProperty', (object) ['target_property' => 'host_name']],
            ['attributes.component', 'MoveProperty', (object) ['target_property' => 'object_name']],
            ['attributes.oem_target_name', 'MoveProperty', (object) ['target_property' => 'object_name']],
            ['attributes.oem_target_type', 'MoveProperty', (object) ['target_property' => 'object_class']],

            // There is something wrong with incoming severity (from OEM msg), check this:
            ['attributes.severity', 'MoveProperty', (object) ['target_property' => 'severity']],
            ['severity', 'MapLookup', (object) [
                'map' => (object) [
                    '1' => 'critical',
                    '2' => 'critical',
                    '4' => 'warning',
                    '6' => 'informational'
                ],
                'when_missing'  => 'default',
                'default_value' => 'informational',
            ]],
        ]);
    }

    // TODO: Legacy, this is now part of the config above
    protected function getModifiersCode()
    {
        // severity 1, 2 -> CRITICAL -> action= write - %s
        // 4 -> warning -> action= write - %s
        // severity=6 oem_clear=true -> OK ->
        //    write - OEMLOG:MOD_SEV:$1:old= new=OK match=0 regex=state=nok severity=[1234].* oem_incident_id=$2;\
        //    write - OEMLOG:OK:$1:$0
        // alle anderen severity=* -> INFO

        return new ModifierChain([
            ['message', new MoveProperty(Settings::fromSerialization(['target_property' => 'attributes']))],
            ['attributes', new SimpleNameValueParser(Settings::fromSerialization([]))],

            // msg: The pluggable database DBMS12AB_SITE1.EXAMPLE.COM_ABCDE is down. OEMIncidentID: 123456
            ['attributes.msg', new MoveProperty(Settings::fromSerialization(['target_property' => 'message']))],

            // 'oem_incident_id' => 654321
            ['attributes.oem_incident_id', new MoveProperty(Settings::fromSerialization(['target_property' => 'sender_event_id']))],

            // id => 123456 != oem_incident_id
            ['attributes.id', new UnsetProperty(Settings::fromSerialization([]))],

            // timestamp => '2021-06-02T13:24:30.276Z'
            ['attributes.timestamp', new ConvertUtcTimeWithMsToTimestamp(new Settings())],

            // state => 'nok'
            ['attributes.state', new UnsetProperty(Settings::fromSerialization([]))],

            // oem_clear => 'false'
            ['attributes.oem_clear', new UnsetProperty(Settings::fromSerialization([]))],

            // There is host_name and oem_host_name
            ['attributes.hostname', new MoveProperty(Settings::fromSerialization(['target_property' => 'host_name']))],
            ['attributes.oem_host_name', new MoveProperty(Settings::fromSerialization(['target_property' => 'host_name']))],

            // component       => 'DBMS12AB_SITE1.EXAMPLE.COM_ABCDE'
            // oem_target_name => 'dbms12ab_site1.example.com_abcde'
            ['attributes.component', new MoveProperty(Settings::fromSerialization(['target_property' => 'object_name']))],
            ['attributes.oem_target_name', new MoveProperty(Settings::fromSerialization(['target_property' => 'object_name']))],

            // oem_target_type => 'oracle_pdb'
            ['attributes.oem_target_type', new MoveProperty(Settings::fromSerialization(['target_property' => 'object_class']))],

            // severity IS numeric, SHOULD be a valid Syslog Severity
            ['attributes.severity', new MoveProperty(Settings::fromSerialization(['target_property' => 'severity']))],

            // oem_incident_ack_by_owner => no
            // oem_incident_status => new
            // oem_issue_type => incident
        ]);
    }

    public function fromPlainObject($obj)
    {
        $properties = $obj;
        $properties->sender_id = $this->senderId; // Enforced property!

        if (! isset($properties->priority)) {
            $properties->priority = Priority::NORMAL;
        }
        // TODO: Move this hardcoded filter
        if ($properties->object_name === 'ngl_dbms_oem_syslog') { // object_name => program
            $this->getModifiers()->process($properties);
        }
        $this->getEnforcedModifiers()->process($properties);
        $event = new Event();
        $event->setProperties((array) $properties);

        return $event;
    }
}
