<?php

namespace Icinga\Module\Eventtracker\Modifier;

use Icinga\Module\Eventtracker\Web\Form\ChannelRuleForm;

class FallbackValue extends BaseModifier
{
    protected static ?string $name = 'Set a fallback value';

    public function transform($object, string $propertyName)
    {
        $value = ObjectUtils::getSpecificValue($object, $propertyName);
        if ($value === null) {
            return $this->getSettings()->getRequired('value');
        }

        return $this->simpleTransform($value);
    }

    public static function extendSettingsForm(ChannelRuleForm $form): void
    {
        $form->addElement('text', 'value', [
            'label' => 'Fallback value',
            'required' => false,
            'description' => 'This value is set in the target property when the value is not set or null'
        ]);
    }
}
