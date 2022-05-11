<?php

namespace Icinga\Module\Eventtracker\Modifier;

use InvalidArgumentException;

class ShortenString extends BaseModifier
{
    protected static $name = 'Shorten String';

    protected $instanceDescriptionPattern = 'Shorten String by {max_length} characters';

    protected function simpleTransform($value)
    {
        $strip = $this->settings->get('strip', 'ending');
        $length = $this->settings->getRequired('max_length');
        if (strlen($value) < $length) {
            return $value;
        }

        switch ($strip) {
            case 'ending':
                return substr($value, 0, $length);
            case 'beginning':
                return substr($value, -1 * $length);
            case 'center':
                $concat = ' ... ';
                $availableLength = ($length - strlen($concat)) / 2;
                return substr($value, 0, ceil($availableLength)) . $concat . substr($value, floor($availableLength));
            default:
                throw new InvalidArgumentException('strip="$strip" is not a valid setting');
        }
    }
}
