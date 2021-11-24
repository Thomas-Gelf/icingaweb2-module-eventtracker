<?php

namespace Icinga\Module\Eventtracker\Check;

use gipfl\Cli\Screen;
use InvalidArgumentException;
use function array_key_exists;
use function ctype_digit;
use function is_int;
use function max;

class PluginState
{
    const STATE_OK = 0;
    const STATE_WARNING = 1;
    const STATE_CRITICAL = 2;
    const STATE_UNKNOWN = 3;

    const NUMERIC_TO_NAME = [
        self::STATE_OK       => 'OK',
        self::STATE_WARNING  => 'WARNING',
        self::STATE_CRITICAL => 'CRITICAL',
        self::STATE_UNKNOWN  => 'UNKNOWN',
    ];

    const NAME_TO_NUMERIC = [
        'OK'       => self::STATE_OK,
        'WARNING'  => self::STATE_WARNING,
        'CRITICAL' => self::STATE_CRITICAL,
        'UNKNOWN'  => self::STATE_UNKNOWN,
    ];

    const STATE_TO_ANSI_COLOR = [
        self::STATE_OK       => 'green',
        self::STATE_WARNING  => 'brown',
        self::STATE_CRITICAL => 'red',
        self::STATE_UNKNOWN  => 'purple',
    ];

    public static function getWorst(...$states)
    {
        $worst = self::STATE_OK;
        foreach ($states as $state) {
            $state = self::getNumeric($state);
            if ($state === self::STATE_CRITICAL) {
                $worst = $state;
            } else {
                $worst = max($worst, $state);
            }
        }

        return $worst;
    }

    public static function getColorized($state)
    {
        $state = self::getNumeric($state);
        return Screen::factory()->colorize(self::getName($state), self::STATE_TO_ANSI_COLOR[$state]);
    }

    public static function getNumeric($state)
    {
        if ((is_int($state) || ctype_digit($state)) && array_key_exists((int) $state, self::NUMERIC_TO_NAME)) {
            return (int) $state;
        }

        if (array_key_exists($state, self::NAME_TO_NUMERIC)) {
            return self::NAME_TO_NUMERIC[$state];
        }

        throw new InvalidArgumentException("$state is not a valid Check Plugin state");
    }

    public static function getName($state)
    {
        return self::NUMERIC_TO_NAME[self::getNumeric($state)];
    }
}
