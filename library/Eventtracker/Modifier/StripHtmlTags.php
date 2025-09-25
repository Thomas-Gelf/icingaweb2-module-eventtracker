<?php

namespace Icinga\Module\Eventtracker\Modifier;

use gipfl\Translation\TranslationHelper;
use ipl\Html\Html;
use ipl\Html\ValidHtml;

class StripHtmlTags extends BaseModifier
{
    use TranslationHelper;

    protected static ?string $name = 'Strip HTML Tags';

    protected function simpleTransform($value)
    {
        return strip_tags($value);
    }

    public function describe(string $propertyName): ValidHtml
    {
        return Html::sprintf(
            $this->translate('Strip HTML tags from %s'),
            Html::tag('strong', $propertyName),
        );
    }
}
