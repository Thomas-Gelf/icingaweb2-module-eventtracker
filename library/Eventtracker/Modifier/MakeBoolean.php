<?php

namespace Icinga\Module\Eventtracker\Modifier;

use InvalidArgumentException;

class MakeBoolean extends BaseModifier
{
    protected static $validStrings = [
        '0'     => false,
        'false' => false,
        'n'     => false,
        'no'    => false,
        '1'     => true,
        'true'  => true,
        'y'     => true,
        'yes'   => true,
    ];

    protected static $name = 'Create a Boolean';

    protected function simpleTransform($value)
    {
        if ($value === false || $value === true || $value === null) {
            return $value;
        }

        if ($value === 0) {
            return false;
        }

        if ($value === 1) {
            return true;
        }

        if (is_string($value)) {
            $value = strtolower($value);

            if (array_key_exists($value, self::$validStrings)) {
                return self::$validStrings[$value];
            }
        }

        switch ($this->settings->get('on_invalid')) {
            case 'null':
                return null;

            case 'false':
                return false;

            case 'true':
                return true;

            case 'fail':
            default:
                throw new InvalidArgumentException(
                    '"%s" cannot be converted to a boolean value',
                    $value
                );
        }
    }
}
