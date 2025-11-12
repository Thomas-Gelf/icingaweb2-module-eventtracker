<?php

namespace Icinga\Module\Eventtracker\Reporting;

use gipfl\Translation\TranslationHelper;

// TODO: enum, once we require PHP 8
class AggregationPeriod
{
    use TranslationHelper;

    public const HOURLY  = 'hourly';
    public const DAILY   = 'daily';
    public const WEEKLY  = 'weekly';
    public const WEEKDAY = 'weekday';
    public const MONTHLY = 'monthly';

    public static function enum(): array
    {
        $t = self::getTranslator();
        return [
            self::HOURLY  => $t->translate('Hourly'),
            self::DAILY   => $t->translate('Daily'),
            self::WEEKLY  => $t->translate('Weekly'),
            self::WEEKDAY => $t->translate('Day of Week'),
            self::MONTHLY => $t->translate('Monthly'),
        ];
    }
}
