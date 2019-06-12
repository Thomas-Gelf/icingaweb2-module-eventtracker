<?php

namespace Icinga\Module\Eventtracker;

use InvalidArgumentException;

class Severity
{
    const DEBUG         = 'debug';
    const INFORMATIONAL = 'informational';
    const NOTICE        = 'notice';
    const WARNING       = 'warning';
    const ERROR         = 'error';
    const CRITICAL      = 'critical';
    const ALERT         = 'alert';
    const EMERGENCY     = 'emergency';

    const ENUM = [
        self::DEBUG         => self::DEBUG,
        self::INFORMATIONAL => self::INFORMATIONAL,
        self::NOTICE        => self::NOTICE,
        self::WARNING       => self::WARNING,
        self::ERROR         => self::ERROR,
        self::CRITICAL      => self::CRITICAL,
        self::ALERT         => self::ALERT,
        self::EMERGENCY     => self::EMERGENCY,
    ];

    public static function isValid($value)
    {
        return \array_key_exists($value, static::ENUM);
    }

    public static function isHighest($value)
    {
        return $value === static::EMERGENCY;
    }

    public static function isLowest($value)
    {
        return $value === static::DEBUG;
    }

    public static function assertValid($priority)
    {
        if (! static::isValid($priority)) {
            throw new InvalidArgumentException("Valid priority expected, got '$priority'");
        }
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
