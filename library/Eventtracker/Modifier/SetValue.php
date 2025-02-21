<?php

namespace Icinga\Module\Eventtracker\Modifier;

use Icinga\Module\Eventtracker\Web\Form;
use ipl\Html\Html;
use ipl\Html\ValidHtml;

class SetValue extends BaseModifier
{
    protected static ?string $name = 'Set a specific value';

    public function transform($object, string $propertyName)
    {
        return $this->getSettings()->getRequired('value');
    }

    public function describe(string $propertyName): ValidHtml
    {
        return Html::sprintf(
            'Set %s = %s',
            Html::tag('strong', $propertyName),
            Html::tag('strong', $this->settings->getRequired('value'))
        );
    }

    public static function extendSettingsForm(Form $form): void
    {
        $form->addElement('text', 'value', [
            'label'       => $form->translate('Value'),
            'required'    => false,
            'description' => $form->translate('Set the chosen property to this value')
        ]);
    }
}
