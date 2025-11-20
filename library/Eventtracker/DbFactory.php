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
            $db = ZfDbConnectionFactory::connection(
                IcingaResource::requireResourceConfig(Config::module('eventtracker')->get('db', 'resource'), 'db')
            );
            assert($db instanceof PdoAdapter);
            self::$db = $db;
        }

        return self::$db;
    }
}
