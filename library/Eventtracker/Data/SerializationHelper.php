<?php

namespace Icinga\Module\Eventtracker\Data;

use Ramsey\Uuid\Uuid;
use stdClass;

class SerializationHelper
{
    /**
     * Hard-coded for now, affects DowntimeRule only
     * @var string[]
     */
    private static array $integers = [
        'duration',
        'max_single_problem_duration',
    ];

    public static function normalizeValue($property, $value)
    {
        if ($value === null) {
            return null;
        }

        if (self::isIntegerProperty($property)) {
            return (int) $value;
        }

        if (self::isBinaryProperty($property)) {
            if (strlen($value) !== 20 && substr($value, 0, 2) === '0x') {
                return hex2bin(substr($value, 2));
            }
        }

        if (self::isBooleanProperty($property)) {
            if ($value === 'y' ||  $value === 'n') {
                return $value;
            }

            return $value ? 'y' : 'n';
        }

        if (self::isUuidProperty($property)) {
            if (strlen($value) !== 16) {
                return Uuid::fromString($value)->getBytes();
            }
        }

        return $value;
    }

    /**
     * @param array|stdClass $properties
     */
    public static function serializeProperties(array $properties): object
    {
        foreach ($properties as $property => &$value) {
            if ($value !== null) {
                if (self::isUuidProperty($property)) {
                    $value = Uuid::fromBytes($value)->toString();
                } elseif (self::isBinaryProperty($property)) {
                    $value = '0x' . bin2hex($value);
                } elseif (self::isBooleanProperty($property)) {
                    $value = $value === 'y';
                }
            }
        }

        return (object) $properties;
    }

    protected static function isIntegerProperty($property): bool
    {
        if (preg_match('/^ts_/', $property)) {
            return true;
        }

        return in_array($property, self::$integers);
    }

    protected static function isBinaryProperty($property): bool
    {
        return $property === 'checksum' || preg_match('/_checksum$/', $property);
    }

    protected static function isUuidProperty($property): bool
    {
        return $property === 'uuid' || preg_match('/_uuid$/', $property);
    }

    protected static function isBooleanProperty($property): bool
    {
        return $property === 'clear' || preg_match('/^(?:is|has)_/', $property);
    }
}
