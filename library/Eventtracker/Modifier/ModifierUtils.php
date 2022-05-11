<?php

namespace Icinga\Module\Eventtracker\Modifier;

use Icinga\Module\Eventtracker\Data\PlainObjectRenderer;

class ModifierUtils
{
    public static function describeModifier($propertyName, Modifier $modifier): string
    {
        $info = $modifier::getName() . "($propertyName";
        $settings = $modifier->getSettings()->jsonSerialize();
        if (! empty((array) $settings)) {
            $info .= ', ' . PlainObjectRenderer::render($settings);
        }

        return "$info)";
    }
}
