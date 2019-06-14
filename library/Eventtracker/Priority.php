<?php

namespace Icinga\Module\Eventtracker;

use InvalidArgumentException;

class Priority
{
    const LOWEST  = 'lowest';
    const LOW     = 'low';
    const NORMAL  = 'normal';
    const HIGH    = 'high';
    const HIGHEST = 'highest';

    const ENUM = [
        self::LOWEST  => self::LOWEST,
        self::LOW     => self::LOW,
        self::NORMAL  => self::NORMAL,
        self::HIGH    => self::HIGH,
        self::HIGHEST => self::HIGHEST,
    ];

    public static function isValid($priority)
    {
        return \array_key_exists($priority, self::ENUM);
    }

    public static function isHighest($priority)
    {
        return $priority === self::HIGHEST;
    }

    public static function isLowest($priority)
    {
        return $priority === self::LOWEST;
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
            case self::HIGH:
                return self::HIGHEST;
            case self::NORMAL:
                return self::HIGH;
            case self::LOW:
                return self::NORMAL;
            case self::LOWEST:
                return self::LOW;
            default:
                self::assertValid($priority);
                return $priority;
        }
    }

    public static function lower($priority)
    {
        switch ($priority) {
            case self::HIGHEST:
                return self::HIGH;
            case self::HIGH:
                return self::NORMAL;
            case self::NORMAL:
                return self::LOW;
            case self::LOW:
                return self::LOWEST;
            default:
                self::assertValid($priority);
                return $priority;
        }
    }
}
