<?php

namespace Icinga\Module\Eventtracker\Modifier;

class ConvertUtcTimeWithMsToTimestamp extends BaseModifier
{
    protected static $name = 'Convert UTC time with ms to timestamp';

    protected function simpleTransform($value)
    {
        $tz = date_default_timezone_get();
        date_default_timezone_set('UTC');
        $tsPattern = '/(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2}:\d{2})\.(\d{3,6})Z$/';
        if (preg_match($tsPattern, $value, $match)) {
            $result = strtotime($match[1] . ' ' . $match[2]) * 1000 + (int) $match[3];
        } else {
            throw new \InvalidArgumentException(
                "'$value' is not a timestamp formatted as %Y-%m-%dT%H:%i:%s.%vZ, like 2021-06-02T13:24:30.276Z"
            );
        }
        date_default_timezone_set($tz);

        return $result;
    }
}
