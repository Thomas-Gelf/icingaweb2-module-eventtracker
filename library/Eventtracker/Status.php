<?php

namespace Icinga\Module\Eventtracker;

use InvalidArgumentException;

class Status
{
    const CLOSED       = 'closed';
    const IN_DOWNTIME  = 'in_downtime';
    const ACKNOWLEDGED = 'acknowledged';
    const OPEN         = 'open';

    const ENUM = [
        self::CLOSED       => self::CLOSED,
        self::IN_DOWNTIME  => self::IN_DOWNTIME,
        self::ACKNOWLEDGED => self::ACKNOWLEDGED,
        self::OPEN         => self::OPEN,
    ];

    public static function isValid($value)
    {
        return \array_key_exists($value, self::ENUM);
    }

    public static function assertValid($value)
    {
        if (! static::isValid($value)) {
            throw new InvalidArgumentException("Valid status expected, got '$value'");
        }
    }
}
