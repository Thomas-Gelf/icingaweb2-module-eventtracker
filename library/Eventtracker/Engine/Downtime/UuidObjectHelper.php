<?php

namespace Icinga\Module\Eventtracker\Engine\Downtime;

use gipfl\ZfDbStore\DbStorable;
use Icinga\Module\Eventtracker\Data\SerializationHelper;

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

    public function set($property, $value)
    {
        $this->reallySet($property, SerializationHelper::normalizeValue($property, $value));
    }

    /**
     * Initialize the stored property at the first loading of the $storable element
     *
     * @param $property
     * @param $value
     */
    public function setStoredProperty($property, $value)
    {
        $this->reallySetStoredProperty($property, SerializationHelper::normalizeValue($property, $value));
    }

    public static function fromSerialization($any): self
    {
        return static::create((array) $any);
    }

    public function jsonSerialize(): object
    {
        return SerializationHelper::serializeProperties($this->getProperties() + $this->getNonDbProperties());
    }

    protected function getNonDbProperties(): array
    {
        return [];
    }
}
