<?php

namespace Icinga\Module\Eventtracker\Modifier;

class UnsetProperty extends BaseModifier
{
    protected static $name = 'Unset Property';

    public function transform($object, $propertyName)
    {
        return new ModifierUnset();
    }
}
