<?php

namespace Icinga\Module\Eventtracker\Modifier;

use InvalidArgumentException;
use JsonSerializable;
use stdClass;
use function get_class;
use function gettype;
use function is_array;
use function is_object;
use function is_scalar;

class Settings implements JsonSerializable
{
    protected $settings = [];

    /**
     * @param stdClass|array $object
     * @return static
     */
    public static function fromSerialization($object)
    {
        $self = new static;
        foreach ((array) $object as $name => $value) {
            $self->set($name, $value);
        }

        return $self;
    }

    public function set($name, $value)
    {
        static::assertSerializableValue($value);
        $this->settings[$name] = $value;
    }

    public function get($name, $default = null)
    {
        if ($this->has($name)) {
            return $this->settings[$name];
        }

        return $default;
    }

    public function getArray($name, $default = [])
    {
        if ($this->has($name)) {
            return (array) $this->settings[$name];
        }

        return $default;
    }

    public function requireArray($name)
    {
        return (array) $this->getRequired(($name));
    }

    public function getAsSettings($name, Settings $default = null)
    {
        if ($this->has($name)) {
            return Settings::fromSerialization($this->settings[$name]);
        }

        if ($default === null) {
            return new Settings();
        }

        return $default;
    }

    public function getRequired($name)
    {
        if ($this->has($name)) {
            return $this->settings[$name];
        }

        throw new InvalidArgumentException("Setting '$name' is not available");
    }

    public function has($name)
    {
        return \array_key_exists($name, $this->settings);
    }

    /**
     * TODO: Check whether json_encode() is faster
     *
     * @param mixed $value
     * @return bool
     */
    protected static function assertSerializableValue($value)
    {
        if ($value === null || is_scalar($value)) {
            return true;
        }
        if (is_object($value)) {
            if ($value instanceof JsonSerializable) {
                return true;
            }

            if ($value instanceof stdClass) {
                foreach ((array) $value as $val) {
                    static::assertSerializableValue($val);
                }

                return true;
            }
        }

        if (is_array($value)) {
            foreach ($value as $val) {
                static::assertSerializableValue($val);
            }

            return true;
        }

        throw new InvalidArgumentException('Serializable value expected, got ' . static::getPhpType($value));
    }

    protected static function getPhpType($var)
    {
        if (is_object($var)) {
            return get_class($var);
        }

        return gettype($var);
    }

    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return (object) $this->settings;
    }
}
