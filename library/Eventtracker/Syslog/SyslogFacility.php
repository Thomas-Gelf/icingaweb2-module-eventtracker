<?php

namespace Icinga\Module\Eventtracker\Syslog;

use InvalidArgumentException;
use function array_key_exists as exists;

class SyslogFacility
{
    const MAP_NUMERIC_TO_NAME = [
        // Posix facilities
        0 => 'kern',    // kernel messages
        1 => 'user',    // user-level messages
        2 => 'mail',    // mail system
        3 => 'daemon',  // system daemons
        4 => 'auth',    // security/authorization messages
        5 => 'syslog',  // messages generated internally by syslogd
        6 => 'lpr',     // 'line printer subsystem',
        7 => 'news',    // 'network news subsystem',
        8 => 'uucp',    // 'UUCP subsystem',
        9 => 'cron',    // 'clock daemon',
        10 => 'authpriv', // 'security/authorization messages',
        11 => 'ftp',      // 'FTP daemon',
        12 => 'NTP subsystem',
        13 => 'log audit',
        14 => 'log alert',
        15 => 'clock daemon (note 2)',

        // local use
        16 => 'local0',
        17 => 'local1',
        18 => 'local2',
        19 => 'local3',
        20 => 'local4',
        21 => 'local5',
        22 => 'local6',
        23 => 'local7',
    ];

    public static function mapNumericToName($numeric)
    {
        if (exists($numeric, self::MAP_NUMERIC_TO_NAME)) {
            return self::MAP_NUMERIC_TO_NAME[$numeric];
        }

        throw new InvalidArgumentException("Numeric Syslog Facility expected, got $numeric");
    }
}
