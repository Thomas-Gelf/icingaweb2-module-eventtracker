<?php

namespace Icinga\Module\Eventtracker\Modifier;

use ipl\Html\Html;
use ipl\Html\ValidHtml;

class UnsetProperty extends BaseModifier
{
    protected static ?string $name = 'Unset Property';

    public function transform($object, string $propertyName)
    {
        return new ModifierUnset();
    }

    public function describe(string $propertyName): ValidHtml
    {
        return Html::sprintf('Unset %s', Html::tag('strong', ($propertyName)));
    }
}
