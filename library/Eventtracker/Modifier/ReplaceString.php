<?php

namespace Icinga\Module\Eventtracker\Modifier;

use Icinga\Module\Eventtracker\Web\Form\ChannelRuleForm;
use InvalidArgumentException;

class ReplaceString extends BaseModifier
{
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
            'label' => $form->translate('Search String'),
            'required' => false,
            'description' => 'String that should be replaced'
        ]);
        $form->addElement('text', 'replacement', [
            'label' => $form->translate('Replacement'),
            'required' => false,
            'description' => 'The value with which the string gets replaced'
        ]);
    }
}
