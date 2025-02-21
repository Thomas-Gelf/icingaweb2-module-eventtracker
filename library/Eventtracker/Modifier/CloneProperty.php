<?php

namespace Icinga\Module\Eventtracker\Modifier;

use Icinga\Module\Eventtracker\Web\Form\ChannelRuleForm;

class CloneProperty extends BaseModifier
{
    protected static ?string $name = 'Clone Property';

    public function transform($object, string $propertyName)
    {
        $currentValue = ObjectUtils::getSpecificValue($object, $propertyName);
        $target = $this->settings->getRequired('target_property');
        ObjectUtils::setSpecificValue(
            $object,
            $target,
            ObjectUtils::deepClone($currentValue)
        );

        return $currentValue;
    }
}
