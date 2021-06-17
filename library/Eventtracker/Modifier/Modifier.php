<?php

namespace Icinga\Module\Eventtracker\Modifier;

interface Modifier
{
    public function __construct(Settings $settings);

    /**
     * @return Settings
     */
    public function getSettings();

    /**
     * @return string
     */
    public static function getName();

    /**
     * @param object $object
     * @param $propertyName
     * @return object
     */
    public function transform($object, $propertyName);
}
