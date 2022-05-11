<?php

namespace Icinga\Module\Eventtracker\Modifier;

use function array_pop;
use function explode;
use function get_called_class;
use function preg_replace;

abstract class BaseModifier implements Modifier
{
    /** @var Settings */
    protected $settings;

    protected static $name;

    protected $instanceDescriptionPattern;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    public static function getName(): string
    {
        if (self::$name === null) {
            $parts = explode('\\', get_called_class());
            return preg_replace('/Modifier$/', '', array_pop($parts));
        }

        return self::$name;
    }

    public function getSettings(): Settings
    {
        return $this->settings;
    }

    public function getInstanceDescription(): ?string
    {
        if ($this->instanceDescriptionPattern === null) {
            return null;
        }

        return PatternHelper::fillPlaceholders($this->instanceDescriptionPattern, $this->settings->jsonSerialize());
    }

    public static function getDescription()
    {
        return null;
    }

    public function transform($object, string $propertyName)
    {
        $value = ObjectUtils::getSpecificValue($object, $propertyName);
        if ($value === null) {
            return null;
        }

        return $this->simpleTransform($value);
    }

    protected function simpleTransform($value)
    {
        return $value;
    }
}
