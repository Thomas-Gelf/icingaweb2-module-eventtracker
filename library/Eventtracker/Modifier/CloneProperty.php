<?php

namespace Icinga\Module\Eventtracker\Modifier;

use Icinga\Module\Eventtracker\Web\Form\ChannelRuleForm;
use ipl\Html\Html;

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

    public static function extendSettingsForm(ChannelRuleForm $form): void
    {
        $form->addElement('text', 'target_property', [
            'label'       => $form->translate('Target Property'),
            'required'    => false,
            'description' => Html::sprintf($form->translate(
                'The given property will be cloned into this target property.'
                . ' You can use %s to access nested properties.'
            ), Html::tag('strong', 'attributes.some.value'))
        ]);
    }
}
