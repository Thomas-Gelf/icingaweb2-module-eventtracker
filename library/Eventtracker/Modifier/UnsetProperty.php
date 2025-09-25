<?php

namespace Icinga\Module\Eventtracker\Modifier;

use gipfl\Translation\TranslationHelper;
use ipl\Html\Html;
use ipl\Html\ValidHtml;

class UnsetProperty extends BaseModifier
{
    use TranslationHelper;

    protected static ?string $name = 'Unset Property';

    public function transform($object, string $propertyName)
    {
        return new ModifierUnset();
    }

    public function describe(string $propertyName): ValidHtml
    {
        return Html::sprintf($this->translate('Unset %s'), Html::tag('strong', ($propertyName)));
    }
}
