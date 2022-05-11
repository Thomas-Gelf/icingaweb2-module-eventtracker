<?php

namespace Icinga\Module\Eventtracker\Modifier;

class SetValue extends BaseModifier
{
    protected static $name = 'Set a specific value';

    public function transform($object, string $propertyName)
    {
        return $this->getSettings()->getRequired('value');
    }
}
