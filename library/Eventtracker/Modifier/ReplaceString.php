<?php

namespace Icinga\Module\Eventtracker\Modifier;

use gipfl\Translation\TranslationHelper;
use Icinga\Module\Eventtracker\Web\Form\ChannelRuleForm;
use ipl\Html\Html;
use ipl\Html\ValidHtml;

class ReplaceString extends BaseModifier
{
    use TranslationHelper;

    protected static ?string $name = 'Replace String';

    protected function simpleTransform($value)
    {
        return str_replace(
            $this->settings->getRequired('search'),
            $this->settings->getRequired('replacement'),
            $value
        );
    }

    public static function extendSettingsForm(ChannelRuleForm $form): void
    {
        $form->addElement('text', 'search', [
            'label'       => $form->translate('Search String'),
            'required'    => false,
            'description' => $form->translate('String that should be replaced'),
        ]);
        $form->addElement('text', 'replacement', [
            'label'       => $form->translate('Replacement'),
            'required'    => false,
            'description' => $form->translate('The value with which the string gets replaced'),
        ]);
    }

    public function describe(string $propertyName): ValidHtml
    {
        return Html::sprintf(
            $this->translate('Replace "%s" in %s with "%s"'),
            $this->settings->getRequired('search'),
            Html::tag('strong', $propertyName),
            $this->settings->getRequired('replacement'),
        );
    }
}
