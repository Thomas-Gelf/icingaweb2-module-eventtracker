<?php

namespace Icinga\Module\Eventtracker\Modifier;

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
}
