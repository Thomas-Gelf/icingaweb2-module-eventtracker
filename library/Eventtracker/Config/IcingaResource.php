<?php

namespace Icinga\Module\Eventtracker\Config;

use Icinga\Application\Config;
use Icinga\Module\Eventtracker\Modifier\Settings;
use InvalidArgumentException;

class IcingaResource
{
    protected static $resources;

    /**
     * @return string[]
     */
    public static function listResourceNames()
    {
        return self::getResources()->keys();
    }

    /**
     * @param string $name
     * @param string $enforcedType
     * @return Settings
     */
    public static function requireResourceConfig($name, $enforcedType = null)
    {
        self::assertResourceExists($name);
        $section = self::getResources()->getSection($name);
        if ($enforcedType !== null && $section->get('type') !== $enforcedType) {
            throw new InvalidArgumentException(sprintf(
                "Resource of type '%s' required, but '%s' is '%s'",
                $enforcedType,
                $name,
                $section->get('type')
            ));
        }

        return Settings::fromSerialization($section->toArray());
    }

    /**
     * @param string $name
     */
    public static function assertResourceExists($name)
    {
        if (! self::getResources()->hasSection($name)) {
            throw new InvalidArgumentException("There is no resource named '$name'");
        }
    }

    public static function forgetConfig()
    {
        self::$resources = null;
    }

    protected static function getResources()
    {
        if (self::$resources === null) {
            self::$resources = Config::app('resources');
        }

        return self::$resources;
    }
}
