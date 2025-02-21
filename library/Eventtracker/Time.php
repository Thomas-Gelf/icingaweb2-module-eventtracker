<?php

namespace Icinga\Module\Eventtracker;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use gipfl\Format\LocalDateFormat;
use gipfl\Format\LocalTimeFormat;
use Icinga\Date\DateFormatter;
use ipl\Html\Html;

class Time
{
    protected static int $lastMilli = 0;

    public static function ago($ms)
    {
        return DateFormatter::timeAgo($ms / 1000);
    }

    public static function since($ms)
    {
        return DateFormatter::timeSince($ms / 1000);
    }

    public static function usFormatter($ms)
    {
        return Html::tag('span', [
            'class' => 'time-ago',
            'title' => DateFormatter::formatDateTime((int) ($ms / 1000))
        ], \date('m/d/Y H:i', (int) ($ms / 1000)));
        // Hint: used to be '%D %I:%M%p', am/pm seems to not work with our locales
    }

    public static function agoFormatted($ms)
    {
        return Html::tag('span', [
            'class' => 'time-ago',
            'title' => DateFormatter::formatDateTime((int) ($ms / 1000))
        ], static::ago($ms));
    }

    public static function info($ms)
    {
        $t = new LocalTimeFormat();
        $d = new LocalDateFormat();

        return Html::sprintf(
            '%s, %s (%s)',
            $d->getFullDay(floor($ms / 1000)),
            $t->getTime(floor($ms / 1000)),
            Html::tag('span', ['class' => 'time-ago'], static::ago($ms))
        );
    }

    public static function unixMilli(): int
    {
        $milli = (int) floor(microtime(true) * 1000);
        if ($milli === self::$lastMilli) {
            $milli = self::$lastMilli + 1;
        }
        self::$lastMilli = $milli;

        return $milli;
    }

    public static function timestampMsToDateTime(int $timestampMs, ?DateTimeZone $timeZone = null): DateTimeImmutable
    {
        return DateTimeImmutable::createFromFormat('u', $timestampMs . '000', $timeZone);
    }

    public static function dateTimeToTimestampMs(DateTimeInterface $dateTime): int
    {
        return (int) floor((int) $dateTime->format('Uu') / 1000);
    }
}
