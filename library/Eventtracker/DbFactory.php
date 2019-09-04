<?php

namespace Icinga\Module\Eventtracker;

use Icinga\Application\Config;
use Icinga\Data\Db\DbConnection;

class DbFactory
{
    /** @var \Zend_Db_Adapter_Pdo_Abstract */
    protected static $db;

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
}
