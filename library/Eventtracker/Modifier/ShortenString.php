<?php

namespace Icinga\Module\Eventtracker\Modifier;

class ShortenString extends BaseModifier
{
    protected static $name = 'Shorten String';

    protected $instanceDescriptionPattern = 'Shorten String by {max_length} characters';

    protected function simpleTransform($value)
    {
        return substr($value, 0, $this->settings->getRequired('max_length'));
    }
}
