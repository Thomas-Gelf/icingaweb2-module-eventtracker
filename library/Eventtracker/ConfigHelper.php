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

    public static function fillPlaceholders($string, Issue $issue)
    {
        return \preg_replace_callback('/({[^}]+})/', function ($match) use ($issue) {
            $property = \trim($match[1], '{}');

            // TODO: check whether Issue has such property
            return $issue->get($property);
        }, $string);
    }
}
