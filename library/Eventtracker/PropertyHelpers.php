<?php

namespace Icinga\Module\Eventtracker;

use InvalidArgumentException;

trait PropertyHelpers
{
    protected $storedProperties = [];

    protected function setStored()
    {
        $this->storedProperties = $this->properties;
    }

    public function hasChanged(): bool
    {
        return $this->storedProperties !== $this->properties;
    }

    public function set(string $key, $value)
    {
        $this->assertPropertyExists($key);
        $this->properties[$key] = $value;
        return $this;
    }

    public function get(string $key, $default = null)
    {
        $this->assertPropertyExists($key);
        if ($this->properties[$key] === null) {
            return $default;
        } else {
            return $this->properties[$key];
        }
    }

    public function setProperties($properties)
    {
        foreach ($properties as $key => $value) {
            $this->set($key, $value);
        }
    }

    public function getModifiedProperties()
    {
        $modified = [];
        foreach ($this->properties as $key => $value) {
            if (\array_key_exists($key, $this->storedProperties)) {
                if ($this->storedProperties[$key] !== $value) {
                    $modified[$key] = $value;
                }
            } else {
                $modified[$key] = $value;
            }
        }

        return $modified;
    }

    public function getModifications(): array
    {
        $modified = $this->getModifiedProperties();
        foreach ($modified as $key => $value) {
            if ($this->isNew()) {
                $modified[$key] = [null, $value];
            } else {
                $modified[$key] = [$this->getStoredProperty($key), $value];
            }
        }

        return $modified;
    }

    public function hasModifiedProperty($key): bool
    {
        if ($this->isNew()) {
            return true;
        }

        return $this->storedProperties[$key] !== $this->properties[$key];
    }

    public function getStoredProperty($key)
    {
        if (\array_key_exists($key, $this->storedProperties)) {
            return $this->storedProperties[$key];
        } else {
            throw new InvalidArgumentException("$key is not a valid stored property");
        }
    }

    public function getProperties(): array
    {
        return $this->properties;
    }

    protected function assertPropertyExists($key)
    {
        if (! array_key_exists($key, $this->properties)) {
            throw new InvalidArgumentException("$key is not a valid property");
        }
    }
}
