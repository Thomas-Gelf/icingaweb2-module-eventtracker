<?php

namespace Icinga\Module\Eventtracker;

use InvalidArgumentException;

trait PropertyHelpers
{
    public function set($key, $value)
    {
        $this->assertPropertyExists($key);
        $this->properties[$key] = $value;
        return $this;
    }

    public function get($key, $default = null)
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

    public function getProperties()
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
