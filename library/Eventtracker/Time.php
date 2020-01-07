<?php

namespace Icinga\Module\Eventtracker;

use Icinga\Date\DateFormatter;
use ipl\Html\Html;

class Time
{
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
        // 12/11/2019 11:41PM
        return Html::tag('span', [
            'class' => 'time-ago',
            'title' => DateFormatter::formatDateTime($ms / 1000)
        ], \strftime('%D %I:%M%p', $ms / 1000));
    }

    public static function agoFormatted($ms)
    {
        return Html::tag('span', [
            'class' => 'time-ago',
            'title' => DateFormatter::formatDateTime($ms / 1000)
        ], static::ago($ms));
    }

    public static function info($ms)
    {
        return Html::sprintf(
            '%s (%s)',
            Html::tag('span', ['class' => 'time-since'], static::since($ms)),
            Html::tag('span', ['class' => 'time-ago'], static::ago($ms))
        );
    }
}
