<?php

namespace Icinga\Module\Eventtracker\Web\Widget;

use gipfl\Format\LocalDateFormat;
use gipfl\Format\LocalTimeFormat;
use gipfl\Translation\StaticTranslator;
use Icinga\Date\DateFormatter;
use Icinga\Module\Eventtracker\Engine\TimePeriod\TimeSlot;
use ipl\Html\Html;
use ipl\Html\HtmlElement;

class DowntimeDescription
{
    protected static ?LocalTimeFormat $timeFormatter = null;
    protected static ?LocalDateFormat $dateFormatter = null;

    public static function getDowntimeActiveInfo(?TimeSlot $activeSlot, ?int $tsTriggered)
    {
        $t = StaticTranslator::get();
        if ($activeSlot) {
            return self::describeDowntimeSlot($activeSlot);
        } elseif ($tsTriggered === null) {
            return $t->translate('Currently no downtime has been scheduled for this rule');
        } elseif ($tsTriggered) { // ??
            return sprintf(
                $t->translate('This Downtime is active since %s'),
                self::niceTsFormat($tsTriggered),
            );
        } else {
            return self::wrapInactive($t->translate('This downtime is currently not active'));
        }
    }

    public static function describeDowntimeSlot(TimeSlot $slot): HtmlElement
    {
        $t = StaticTranslator::get();
        if ($slot->end) {
            return self::wrapActive(Html::sprintf(
                $t->translate('Currently active, slot started %s, and finishes %s'),
                Html::tag('span', [
                    'class' => 'time-ago',
                    'title' => $slot->start->format('Y-m-d H:i:s'),
                ], DateFormatter::timeAgo($slot->start->getTimestamp())),
                Html::tag('span', [
                    'class' => 'time-until',
                    'title' => $slot->end->format('Y-m-d H:i:s'),
                ], DateFormatter::timeUntil($slot->end->getTimestamp())),
            ));
        }

        return self::wrapActive(Html::sprintf(
            $t->translate('Currently active, slot started %s'),
            Html::tag('span', [
                'class' => 'time-ago',
                'title' => $slot->start->format('Y-m-d H:i:s'),
            ], DateFormatter::timeAgo($slot->start->getTimestamp()))
        ));
    }

    protected static function wrapActive($content): HtmlElement
    {
        return Html::tag('span', ['class' => 'downtime-active'], $content);
    }

    protected static function wrapInactive($content):HtmlElement
    {
        return Html::tag('span', ['class' => 'downtime-inactive'], $content);
    }

    protected static function niceTsFormat($ts): string
    {
        $ts = $ts / 1000;
        return self::getDateFormatter()->getFullDay($ts) . ' ' . self::getTimeFormatter()->getShortTime($ts);
    }

    protected static function getTimeFormatter(): LocalTimeFormat
    {
        return self::$timeFormatter ??= new LocalTimeFormat();
    }

    protected static function getDateFormatter(): LocalDateFormat
    {
        return self::$dateFormatter ??= new LocalDateFormat();
    }
}
