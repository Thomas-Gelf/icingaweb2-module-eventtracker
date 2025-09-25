<?php

namespace Icinga\Module\Eventtracker\Modifier;

use gipfl\Translation\TranslationHelper;
use Icinga\Module\Eventtracker\Web\Form\ChannelRuleForm;
use ipl\Html\Html;
use ipl\Html\ValidHtml;

class SetValue extends BaseModifier
{
    use TranslationHelper;

    protected static ?string $name = 'Set a specific value';

    public function transform($object, string $propertyName)
    {
        return $this->getSettings()->getRequired('value');
    }

    public function describe(string $propertyName): ValidHtml
    {
        return Html::sprintf(
            $this->translate('Set %s = %s'),
            Html::tag('strong', $propertyName),
            Html::tag('strong', $this->settings->getRequired('value'))
        );
    }

    public static function extendSettingsForm(ChannelRuleForm $form): void
    {
        $form->addElement('text', 'value', [
            'label'       => $form->translate('Value'),
            'required'    => true,
            'description' => $form->translate('Set the chosen property to this value')
        ]);
    }
}
