<?php

namespace Icinga\Module\Eventtracker;

use gipfl\ZfDb\Adapter\Adapter as Db;
use Icinga\Application\Config;
use Icinga\Module\Eventtracker\Config\IcingaResource;
use Icinga\Module\Eventtracker\Db\ZfDbConnectionFactory;

class DbFactory
{
    /** @var Db */
    protected static $db;

    public static function db(): Db
    {
        if (self::$db === null) {
            self::$db = ZfDbConnectionFactory::connection(
                IcingaResource::requireResourceConfig(Config::module('eventtracker')->get('db', 'resource'), 'db')
            );
        }

        return self::$db;
    }
}
