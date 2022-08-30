<?php

namespace Icinga\Module\Eventtracker\Modifier;

class CloneProperty extends BaseModifier
{
    protected static $name = 'Clone Property';

    public function transform($object, string $propertyName)
    {
        $target = $this->settings->getRequired('target_property');
        ObjectUtils::setSpecificValue(
            $object,
            $target,
            ObjectUtils::deepClone(ObjectUtils::getSpecificValue($object, $propertyName))
        );
        return ObjectUtils::getSpecificValue($object, $propertyName);
    }
}
