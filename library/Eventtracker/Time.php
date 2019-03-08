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

    public static function agoFormatted($ms)
    {
        return Html::tag('span', ['class' => 'time-ago'], static::ago($ms));
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
