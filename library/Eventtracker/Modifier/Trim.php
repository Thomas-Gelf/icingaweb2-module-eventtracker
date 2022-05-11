<?php

namespace Icinga\Module\Eventtracker\Modifier;

class Trim extends BaseModifier
{
    protected static $name = 'Trim a string';

    protected function simpleTransform($value)
    {
        return trim($value);
    }
}
