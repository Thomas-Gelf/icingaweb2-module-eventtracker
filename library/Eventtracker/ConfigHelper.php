<?php

namespace Icinga\Module\Eventtracker;

class ConfigHelper
{
    public static function getList($value)
    {
        if (\strlen($value)) {
            $parts = preg_split('/\s*,\s*/', $value, -1, \PREG_SPLIT_NO_EMPTY);

            return $parts;
        } else {
            return [];
        }
    }
}
