<?php

namespace Icinga\Module\Eventtracker\Modifier;

use gipfl\Translation\TranslationHelper;
use ipl\Html\Html;
use ipl\Html\ValidHtml;

class Trim extends BaseModifier
{
    use TranslationHelper;

    protected static ?string $name = 'Trim a string';

    protected function simpleTransform($value)
    {
        return trim($value);
    }

    public function describe(string $propertyName): ValidHtml
    {
        return Html::sprintf(
            $this->translate('Trim %s'),
            Html::tag('strong', $propertyName),
        );
    }
}
