<?php

namespace Icinga\Module\Eventtracker;

use gipfl\ZfDb\Adapter\Pdo\PdoAdapter;
use Icinga\Application\Config;
use Icinga\Module\Eventtracker\Config\IcingaResource;
use Icinga\Module\Eventtracker\Db\ZfDbConnectionFactory;

class DbFactory
{
    protected static ?PdoAdapter $db = null;

    public static function db(): PdoAdapter
    {
        if (self::$db === null) {
            self::$db = ZfDbConnectionFactory::connection(
                IcingaResource::requireResourceConfig(Config::module('eventtracker')->get('db', 'resource'), 'db')
            );
        }

        return self::$db;
    }
}
