<?php

namespace Icinga\Module\Eventtracker\Modifier;

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
}
