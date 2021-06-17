<?php

namespace Icinga\Module\Eventtracker\Modifier;

class PatternHelper
{
    /**
     * @param $string
     * @param object $object
     * @return string|null
     */
    public static function fillPlaceholders($string, $object)
    {
        return \preg_replace_callback('/({[^}]+})/', function ($match) use ($object) {
            $property = \trim($match[1], '{}');
            list($property, $modifier) = static::extractPropertyModifier($property);
            if (property_exists($object, $property)) {
                $value = $object->$property;
            } else {
                $value = null;
            }
            if ($value === null) {
                return static::missingProperty($property);
            }
            static::applyPropertyModifier($value, $modifier);

            return $value;
        }, $string);
    }

    protected static function applyPropertyModifier(&$value, $modifier)
    {
        // Hint: $modifier could be null
        switch ($modifier) {
            case 'lower':
                $value = \strtolower($value);
                break;
        }
    }

    protected static function missingProperty($property)
    {
        return '{' . $property . '}';
    }

    protected static function extractPropertyModifier($property)
    {
        $modifier = null;
        // TODO: make property modifiers dynamic
        if (\preg_match('/:lower$/', $property)) {
            $property = \preg_replace('/:lower$/', '', $property);
            $modifier = 'lower';
        }

        return [$property, $modifier];
    }
}
