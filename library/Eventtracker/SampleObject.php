<?php

namespace Icinga\Module\Eventtracker;

use gipfl\Json\JsonString;
use Icinga\Module\Eventtracker\Syslog\SyslogParser;

class SampleObject
{
    protected $syslog;
    public function __construct()
    {
    }
    public static function getSampleObject($type)
    {
        switch ($type) {
            case 'syslog':
                return self::createSyslogOutput();
                break;
            case 'json':
                return self::createJsonStringOutput();
            default:
                return null;
        }
    }

    private static function createSyslogOutput()
    {
        return SyslogParser::parseLine(
            'Jan 11 13:12:54 goj oem_syslog[2837832]: timestamp=2025-01-11T12:12:54.560Z'
            . ' hostname=kri.example.com component=kri.example.com id=2837644 state=nok severity=2 oem_clear=false'
            . ' oem_host_name=kri.example.com oem_incident_ack_by_owner=no url=https://ip.gelf.net oem_incident_id=4996'
            . ' oem_incident_status=new'
            . ' oem_issue_type=incident oem_target_name=kri.example.com oem_target_type=host msg=Alert; Value=7;'
            . ' String <Returncode:> with values <> 0 found in /var/log/dbms/load_dbclone_for_oracle_mssql.log!'
            . ' OEMIncidentID: 4996'
        );
    }

    private static function createJsonStringOutput()
    {
        return JsonString::decode(
            '{' . "\n"
            . '    "host_name": "goj",' . "\n"
            . '    "object_name": "oem_syslog",' . "\n"
            . '    "object_class": "user",' . "\n"
            . '    "severity": "critical",' . "\n"
            . '    "priority": null,' . "\n"
            . '    "message": "timestamp=2025-02-02T13:24:30.276Z hostname=atb.example.com'
            . ' component=DBMS03_SITE1.EXAMPLE id=125690 state=nok severity=1 oem_clear=false'
            . ' oem_host_name=atb.example.com oem_incident_ack_by_owner=no oem_incident_id=144826'
            . ' oem_incident_status=new oem_issue_type=incident oem_target_name=dbms03_site1.example'
            . ' oem_target_type=oracle_pdb msg=The pluggable database DBMS03_SITE1.EXAMPLE is down.'
            . ' OEMIncidentID: 144826",' . "\n"
            . '    "attributes": {' . "\n"
            . '        "syslog_sender_pid": 125795' . "\n"
            . '    }' . "\n"
            . '}' . "\n"
        );
    }

}
