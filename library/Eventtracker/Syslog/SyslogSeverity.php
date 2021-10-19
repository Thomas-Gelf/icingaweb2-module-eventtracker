<?php

namespace Icinga\Module\Eventtracker\Syslog;

use InvalidArgumentException;
use function array_key_exists as exists;
use function ctype_digit;
use function is_int;
use function is_string;

class SyslogSeverity
{
    const DEBUG         = 'debug';
    const INFORMATIONAL = 'informational';
    const NOTICE        = 'notice';
    const WARNING       = 'warning';
    const ERROR         = 'error';
    const CRITICAL      = 'critical';
    const ALERT         = 'alert';
    const EMERGENCY     = 'emergency';

    const MAP_NUMERIC_TO_NAME = [
        0 => self::EMERGENCY,
        1 => self::ALERT,
        2 => self::CRITICAL,
        3 => self::ERROR,
        4 => self::WARNING,
        5 => self::NOTICE,
        6 => self::INFORMATIONAL,
        7 => self::DEBUG,
    ];

    const MAP_NAME_TO_NUMERIC = [
        self::EMERGENCY     => 0,
        self::ALERT         => 1,
        self::CRITICAL      => 2,
        self::ERROR         => 3,
        self::WARNING       => 4,
        self::NOTICE        => 5,
        self::INFORMATIONAL => 6,
        self::DEBUG         => 7,
    ];

    public static function isValid($value)
    {
        return exists($value, self::MAP_NAME_TO_NUMERIC)
            || exists($value, self::MAP_NUMERIC_TO_NAME);
    }

    public static function isHighest($value)
    {
        return $value === static::EMERGENCY;
    }

    public static function isLowest($value)
    {
        return $value === static::DEBUG;
    }

    public static function maxNumeric($severity1, $severity2)
    {
        $severity1 = static::wantNumeric($severity1);
        $severity2 = static::wantNumeric($severity2);

        return $severity1 > $severity2 ? $severity1 : $severity2;
    }

    public static function maxName($severity1, $severity2)
    {
        return static::mapNameToNumeric(self::maxNumeric($severity1, $severity2));
    }

    public static function assertValid($severity)
    {
        if (! static::isValid($severity)) {
            throw new InvalidArgumentException("Valid severity expected, got '$severity'");
        }
    }

    public static function wantNumeric($severity)
    {
        if (is_int($severity)) {
            self::assertValid($severity);
            return $severity;
        }
        if (is_string($severity) && ctype_digit($severity)) {
            $severity = (int) $severity;
            self::assertValid($severity);
            return $severity;
        }

        return self::mapNameToNumeric($severity);
    }

    public static function wantName($severity)
    {
        if (is_string($severity)) {
            if (ctype_digit($severity)) {
                return static::wantName((int) $severity);
            } else {
                self::assertValid($severity);
                return $severity;
            }
        }

        return static::mapNumericToName($severity);
    }

    public static function mapNumericToName($numeric)
    {
        if (exists($numeric, self::MAP_NUMERIC_TO_NAME)) {
            return self::MAP_NUMERIC_TO_NAME[$numeric];
        }

        throw new InvalidArgumentException("Numeric Syslog Severity expected, got $numeric");
    }

    public static function mapNameToNumeric($name)
    {
        if (exists($name, self::MAP_NAME_TO_NUMERIC)) {
            return self::MAP_NAME_TO_NUMERIC[$name];
        }

        throw new InvalidArgumentException("Named Syslog Severity expected, got $name");
    }

    public static function raise($priority)
    {
        switch ($priority) {
            case static::ALERT:
                return static::EMERGENCY;
            case self::CRITICAL:
                return static::ALERT;
            case self::ERROR:
                return static::CRITICAL;
            case self::WARNING:
                return static::ERROR;
            case self::NOTICE:
                return static::WARNING;
            case self::INFORMATIONAL:
                return static::NOTICE;
            case self::DEBUG:
                return static::INFORMATIONAL;
            default:
                static::assertValid($priority);
                return $priority;
        }
    }

    public static function lower($severity)
    {
        switch ($severity) {
            case static::EMERGENCY:
                return static::ALERT;
            case self::ALERT:
                return static::CRITICAL;
            case static::CRITICAL:
                return static::ERROR;
            case static::ERROR:
                return static::WARNING;
            case static::WARNING:
                return static::NOTICE;
            case static::NOTICE:
                return static::INFORMATIONAL;
            case static::INFORMATIONAL:
                return static::DEBUG;
            default:
                static::assertValid($severity);
                return $severity;
        }
    }
}
