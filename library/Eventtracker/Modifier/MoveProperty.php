<?php

namespace Icinga\Module\Eventtracker\Modifier;

class MoveProperty extends BaseModifier
{
    protected static $name = 'Move Property';

    public function transform($object, string $propertyName)
    {
        $target = $this->settings->getRequired('target_property');
        ObjectUtils::setSpecificValue($object, $target, ObjectUtils::getSpecificValue($object, $propertyName));
        return new ModifierUnset();
    }
}
