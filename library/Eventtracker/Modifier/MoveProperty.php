<?php

namespace Icinga\Module\Eventtracker\Modifier;

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
}
