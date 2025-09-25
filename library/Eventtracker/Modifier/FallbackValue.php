<?php

namespace Icinga\Module\Eventtracker\Modifier;

use gipfl\Translation\TranslationHelper;
use Icinga\Module\Eventtracker\Web\Form\ChannelRuleForm;
use ipl\Html\Html;
use ipl\Html\ValidHtml;

class FallbackValue extends BaseModifier
{
    use TranslationHelper;

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
            'label'       => $form->translate('Fallback value'),
            'required'    => true,
            'description' => $form->translate(
                'This value is set in the target property when the value is not set or null'
            )
        ]);
    }

    public function describe(string $propertyName): ValidHtml
    {
        return Html::sprintf(
            $this->translate('Set %s to %s, if not set (or null)'),
            Html::tag('strong', $propertyName),
            Html::tag('strong', $this->settings->getRequired('value')),
        );
    }
}
