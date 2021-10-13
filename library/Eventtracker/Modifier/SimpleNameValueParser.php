<?php

namespace Icinga\Module\Eventtracker\Modifier;

class SimpleNameValueParser extends BaseModifier
{
    protected static $name = 'Parse "key=value" strings';

    protected function simpleTransform($value)
    {
        $parts = explode(' ', $value);
        $properties = [];

        // TODO: Improve this.
        foreach ($parts as $part) {
            if (($pos = strpos($part, '=')) === false) {
                $key = 'invalid';
                if (isset($properties[$key])) {
                    $properties[$key] .= " $part";
                } else {
                    $properties[$key] = $part;
                }
            } else {
                $key = substr($part, 0, $pos);
                $properties[$key] = substr($part, $pos + 1);
            }
        }

        return (object) $properties;
    }
}
