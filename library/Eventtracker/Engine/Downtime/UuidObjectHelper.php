<?php

namespace Icinga\Module\Eventtracker\Engine\Downtime;

use gipfl\ZfDbStore\DbStorable;
use Icinga\Module\Eventtracker\Issue;
use Ramsey\Uuid\Uuid;

trait UuidObjectHelper
{
    use DbStorable {
        DbStorable::set as reallySet;
        DbStorable::setStoredProperty as reallySetStoredProperty;
    }

    public function hasModifiedProperty($key): bool
    {
        if ($this->isNew()) {
            return true;
        }

        return $this->storedProperties[$key] !== $this->properties[$key];
    }

    protected function isIntegerProperty($property): bool
    {
        if (preg_match('/^ts_/', $property)) {
            return true;
        }

        if (property_exists($this, 'integers')) {
            return in_array($property, $this->integers);
        }

        return false;
    }

    protected function isBinaryProperty($property): bool
    {
        return $property === 'checksum' || preg_match('/_checksum$/', $property);
    }

    protected function isUuidProperty($property): bool
    {
        return $property === 'uuid' || preg_match('/_uuid$/', $property);
    }

    protected function isBooleanProperty($property): bool
    {
        return $property === 'clear' || preg_match('/^(?:is|has)_/', $property);
    }

    public function set($property, $value)
    {
        $this->reallySet($property, $this->normalizeValue($property, $value));
    }

    protected function normalizeValue($property, $value)
    {
        if ($value === null) {
            return null;
        }

        if ($this->isIntegerProperty($property)) {
            return (int) $value;
        }

        if ($this->isBinaryProperty($property)) {
            if (strlen($value) !== 20 && substr($value, 0, 2) === '0x') {
                return hex2bin(substr($value, 2));
            }
        }

        if ($this->isBooleanProperty($property)) {
            if ($value === 'y' ||  $value === 'n') {
                return $value;
            }

            return $value ? 'y' : 'n';
        }

        if ($this->isUuidProperty($property)) {
            if (strlen($value) !== 16) {
                return Uuid::fromString($value)->getBytes();
            }
        }

        return $value;
    }

    /**
     * Initialize the stored property at the first loading of the $storable element
     *
     * @param $property
     * @param $value
     */
    public function setStoredProperty($property, $value)
    {
        $this->reallySetStoredProperty($property, $this->normalizeValue($property, $value));
    }

    public static function fromSerialization($any): self
    {
        return static::create((array) $any);
    }

    public function jsonSerialize(): object
    {
        if ($this instanceof Issue) {
            var_dump($this->createdNow);
            var_dump($this->getNonDbProperties());
        }
        return $this->serializeProperties($this->getProperties() + $this->getNonDbProperties());
    }

    protected function getNonDbProperties(): array
    {
        return [];
    }

    protected function serializeProperties(array $properties): object
    {
        foreach ($properties as $property => &$value) {
            if ($this instanceof Issue) {
                var_dump("$property = $value");
            }
            if ($value !== null) {
                if ($this->isUuidProperty($property)) {
                    $value = Uuid::fromBytes($value)->toString();
                } elseif ($this->isBinaryProperty($property)) {
                    $value = '0x' . bin2hex($value);
                } elseif ($this->isBooleanProperty($property)) {
                    $value = $value === 'y';
                }
            }
        }

        return (object) $properties;
    }
}
