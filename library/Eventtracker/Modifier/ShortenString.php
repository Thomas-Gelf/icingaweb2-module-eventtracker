<?php

namespace Icinga\Module\Eventtracker\Modifier;

use InvalidArgumentException;
use ipl\Html\Html;

class ShortenString extends BaseModifier
{
    protected static ?string $name = 'Shorten String';

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

    public function describe(string $propertyName)
    {
        return Html::sprintf(
            'Shorten String in %s by %s characters',
            Html::tag('strong', $propertyName),
            $this->settings->getRequired('max_length')
        );
    }
}
