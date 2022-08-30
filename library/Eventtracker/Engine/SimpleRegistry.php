<?php

namespace Icinga\Module\Eventtracker\Engine;

use InvalidArgumentException;

abstract class SimpleRegistry implements Registry
{
    protected $implementations = [];

    public function getInstance($identifier): Task
    {
        $class = $this->getClassName($identifier);

        return new $class;
    }

    public function getClassName($identifier): string
    {
        if (! isset($this->implementations[$identifier])) {
            throw new InvalidArgumentException("No class found for $identifier");
        }

        return $this->implementations[$identifier];
    }

    public function listImplementations(): array
    {
        $implementations = [];
        /** @var $class Task */
        foreach ($this->implementations as $key => $class) {
            $implementations[$key] = $class::getLabel();
        }

        return $implementations;
    }
}
