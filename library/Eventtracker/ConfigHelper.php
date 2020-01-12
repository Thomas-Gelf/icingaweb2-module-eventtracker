<?php

namespace Icinga\Module\Eventtracker;

class ConfigHelper
{
    public static function getList($value)
    {
        if (\strlen($value)) {
            $parts = \preg_split('/\s*,\s*/', $value, -1, \PREG_SPLIT_NO_EMPTY);

            return $parts;
        } else {
            return [];
        }
    }

    /**
     * @param $string
     * @param Event|Issue|object $issue
     * @return string|null
     */
    public static function fillPlaceholders($string, $issue)
    {
        return \preg_replace_callback('/({[^}]+})/', function ($match) use ($issue) {
            $property = \trim($match[1], '{}');
            $modifier = null;
            // TODO: make property modifiers dynamic
            if (\preg_match('/:lower$/', $property)) {
                $property = \preg_replace('/:lower$/', '', $property);
                $modifier = 'lower';
            }
            // TODO: check whether Issue has such property, and eventually use an interface
            if ($issue instanceof Issue || $issue instanceof Event) {
                $value = $issue->get($property);
            } else {
                $value = $issue->$property;
            }

            switch ($modifier) {
                case 'lower':
                    $value = \strtolower($value);
                    break;
            }

            return $value;
        }, $string);
    }
}
