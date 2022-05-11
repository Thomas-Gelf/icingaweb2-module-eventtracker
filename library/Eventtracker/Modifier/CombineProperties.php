<?php

namespace Icinga\Module\Eventtracker\Modifier;

class CombineProperties extends BaseModifier
{
    protected static $name = 'Combine multiple properties';

    public function transform($object, string $propertyName)
    {
        return ObjectUtils::fillVariables($this->settings->getRequired('pattern'), $object);
    }
}
