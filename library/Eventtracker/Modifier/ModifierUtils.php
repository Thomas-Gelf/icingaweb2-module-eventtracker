<?php

namespace Icinga\Module\Eventtracker\Modifier;

use Icinga\Module\Eventtracker\Data\PlainObjectRenderer;

class ModifierUtils
{
    public static function getFullModifierDescription(string $propertyName, Modifier $modifier): string
    {
        $info = $modifier::getName() . "($propertyName";
        $settings = $modifier->getSettings()->jsonSerialize();
        if (! empty((array) $settings)) {
            $info .= ', ' . PlainObjectRenderer::render($settings);
        }

        return "$info)";
    }

    public static function getShortConfigChecksum(string $propertyName, Modifier $modifier): string
    {
        return substr(sha1(static::getFullModifierDescription($propertyName, $modifier)), 0, 7);
    }
}
