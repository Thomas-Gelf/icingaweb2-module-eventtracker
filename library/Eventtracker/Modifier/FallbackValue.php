<?php

namespace Icinga\Module\Eventtracker\Modifier;

class FallbackValue extends BaseModifier
{
    protected static $name = 'Set a fallback value';

    public function transform($object, string $propertyName)
    {
        $value = ObjectUtils::getSpecificValue($object, $propertyName);
        if ($value === null) {
            return $this->getSettings()->getRequired('value');
        }

        return $this->simpleTransform($value);
    }
}
