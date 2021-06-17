<?php

namespace Icinga\Module\Eventtracker\Syslog;

class SyslogParser
{
    const MONTHS_SHORT = [
        'Jan',
        'Feb',
        'Mar',
        'Apr',
        'May',
        'Jun',
        'Jul',
        'Aug',
        'Sep',
        'Oct',
        'Nov',
        'Dec',
    ];

    public static function parseLine($line)
    {
        // Unchecked, from RFC: The total length of the packet MUST be 1024 bytes or less.
        if (preg_match('/^<(\d{1,3})>/', $line, $match)) {
            $priVal = (int) $match[1];
            $line = substr($line, strlen($match[0]));
        } else {
            $priVal = 13;
            // 'Got no syslog header: ' . $line;
        }

        $facility = $priVal >> 3;
        $severity = $priVal & 0x07;

        $datetime = false;
        if (in_array(substr($line, 0, 3), self::MONTHS_SHORT)) {
            try {
                // TODO: verify structure with regex
                $datetime = new \DateTime(substr($line, 0, 15));
            } catch (\Exception $e) {
                // TODO:
                // $logger->log($e->getMessage());
            }
            if ($datetime instanceof \DateTime) {
                $line = substr($line, 16);
            } else {
                $datetime = new \DateTime();
            }
        } else {
            if (false !== ($nextSpace = strpos($line, ' '))) {
                try {
                    $datetime = new \DateTime(substr($line, 0, $nextSpace));
                    if ($datetime instanceof \DateTime) {
                        $line = substr($line, $nextSpace);
                    }
                } catch (\Exception $e) {
                    // TODO:
                    // $logger->log($e->getMessage());
                }
            }
        }
        if (! $datetime instanceof \DateTime) {
            $datetime = new \DateTime();
        }

        if (preg_match('/^([^\s]+)\s/', $line, $match)) {
            $host = $match[1];
            $line = substr($line, strlen($match[0]));
        } else {
            $host = null;
        }
        if (preg_match('/^([^\[]+)\[(\d+)]:\s+/', $line, $match)) {
            $program = $match[1];
            $pid = (int) $match[2];
            $line = substr($line, strlen($match[0]));
        } else {
            $program = null;
            $pid = null;
        }

        $result = (object) [
            'host_name'    => $host,
            'object_name'  => $program,
            'object_class' => SyslogFacility::mapNumericToName($facility),
            'severity'     => SyslogSeverity::mapNumericToName($severity),
            'priority'     => null,
            'message'      => $line,
            // 'timestamp_ms' => (int) floor((float) $datetime->format('U\.u') * 1000),
        ];

        if ($pid !== null) {
            $result->attributes = (object) ['pid' => $pid];
        }

        return $result;
    }
}
