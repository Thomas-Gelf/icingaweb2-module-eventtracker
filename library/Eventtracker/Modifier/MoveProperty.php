<?php

namespace Icinga\Module\Eventtracker\Modifier;

use Icinga\Module\Eventtracker\Web\Form\ChannelRuleForm;
use ipl\Html\Html;
use ipl\Html\ValidHtml;

class MoveProperty extends BaseModifier
{
    protected static ?string $name = 'Move Property';

    public function transform($object, string $propertyName)
    {
        $target = $this->settings->getRequired('target_property');
        ObjectUtils::setSpecificValue($object, $target, ObjectUtils::getSpecificValue($object, $propertyName));
        return new ModifierUnset();
    }

    public function describe(string $propertyName): ValidHtml
    {
        return Html::sprintf(
            'Move %s to %s',
            Html::tag('strong', $propertyName),
            Html::tag('strong', $this->settings->getRequired('target_property'))
        );
    }

    public static function extendSettingsForm(ChannelRuleForm $form): void
    {
        $form->addElement('text', 'target_property', [
        'label' => $form->translate('Target Property'),
        'required' => false,
        'description' => $form->translate('The value of the given property'
        . ' will be moved to to target properties value'),
            ]);
    }
}
