<?php

namespace Icinga\Module\Eventtracker\Modifier;

interface Modifier
{
    public function __construct(Settings $settings);
    public function getSettings(): Settings;
    public static function getName(): string;
    public function transform(object $object, string $propertyName);
}
