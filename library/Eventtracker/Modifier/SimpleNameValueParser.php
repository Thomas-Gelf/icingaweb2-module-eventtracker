<?php

namespace Icinga\Module\Eventtracker\Modifier;

class SimpleNameValueParser extends BaseModifier
{
    protected static $name = 'Parse "key=value" strings';

    protected function simpleTransform($value)
    {
        $parts = explode(' ', $value);
        $properties = [];
        $key = 'invalid';
        foreach ($parts as $part) {
            if (($pos = strpos($part, '=')) === false) {
                $properties[$key] .= " $part";
            } else {
                $key = substr($part, 0, $pos);
                $properties[$key] = substr($part, $pos + 1);
            }
        }

        return (object) $properties;
    }
}
