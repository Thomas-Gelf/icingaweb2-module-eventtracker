<?php

namespace Icinga\Module\Eventtracker\Modifier;

class StripHtmlTags extends BaseModifier
{
    protected static ?string $name = 'Strip HTML Tags';

    protected function simpleTransform($value)
    {
        return strip_tags($value);
    }
}
