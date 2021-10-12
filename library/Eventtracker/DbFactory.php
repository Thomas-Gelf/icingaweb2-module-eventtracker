<?php

namespace Icinga\Module\Eventtracker;

use gipfl\ZfDb\Db;
use Icinga\Application\Config;
use Icinga\Data\Db\DbConnection;
use Icinga\Module\Eventtracker\Config\IcingaResource;
use Icinga\Module\Eventtracker\Db\ZfDbConnectionFactory;

class DbFactory
{
    /** @var \Zend_Db_Adapter_Pdo_Abstract */
    protected static $db;

    /** @var Db */
    protected static $gipflDb;

    /**
     * @return \Zend_Db_Adapter_Pdo_Abstract
     */
    public static function db()
    {
        if (self::$db === null) {
            self::$db = DbConnection::fromResourceName(
                Config::module('eventtracker')->get('db', 'resource')
            )->getDbAdapter();
        }

        return self::$db;
    }

    /**
     * @return \gipfl\ZfDb\Adapter\Adapter
     * @throws \Icinga\Exception\ConfigurationError
     */
    public static function gipflDb()
    {
        if (self::$gipflDb === null) {
            self::$gipflDb = ZfDbConnectionFactory::connection(
                IcingaResource::requireResourceConfig(Config::module('eventtracker')->get('db', 'resource'), 'db')
            );
        }

        return self::$gipflDb;
    }
}
