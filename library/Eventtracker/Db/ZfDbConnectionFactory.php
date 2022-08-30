<?php

namespace Icinga\Module\Eventtracker\Db;

use DateTime;
use DateTimeZone;
use gipfl\ZfDb\Adapter\Adapter;
use gipfl\ZfDb\Adapter\Pdo\Ibm;
use gipfl\ZfDb\Adapter\Pdo\Mssql;
use gipfl\ZfDb\Adapter\Pdo\Mysql;
use gipfl\ZfDb\Adapter\Pdo\Oci;
use gipfl\ZfDb\Adapter\Pdo\Pgsql;
use gipfl\ZfDb\Adapter\Pdo\Sqlite;
use gipfl\ZfDb\Db;
use Icinga\Module\Eventtracker\Modifier\Settings;
use PDO;
use RuntimeException;

class ZfDbConnectionFactory
{
    const DEFAULT_PORTS = [
        'mssql'  => 1433,
        'mysql'  => 3306,
        'oci'    => 1521,
        'oracle' => 1521,
        'pgsql'  => 5432,
        'ibm'    => 50000,
        // 'sqlite' => none
    ];

    const DB_ADAPTERS = [
        'mysql'  => Mysql::class,
        'mssql'  => Mssql::class,
        'oracle' => Oci::class,
        'pgsql'  => Pgsql::class,
        'sqlite' => Sqlite::class,
        'ibm'    => Ibm::class,
    ];

    const DEFAULT_ADAPTER_OPTIONS = [
        Db::AUTO_QUOTE_IDENTIFIERS => false,
        Db::CASE_FOLDING           => Db::CASE_LOWER
    ];

    const DEFAULT_PDO_OPTIONS = [
        PDO::ATTR_TIMEOUT    => 10,
        PDO::ATTR_CASE       => PDO::CASE_LOWER,
        PDO::ATTR_ERRMODE    => PDO::ERRMODE_EXCEPTION
    ];

    protected static function getDbType(Settings $config)
    {
        return strtolower($config->get('db', 'mysql'));
    }

    /**
     * @param Settings $config
     * @return Adapter
     */
    public static function connection(Settings $config)
    {
        $adapterParams = self::getMainAdapterParams($config);
        $adapter = static::getAdapterClass($config);
        switch ($adapter) {
            case Mysql::class:
                $adapterParams['driver_options'] += static::getMySqlSslParams($config);
                $adapterParams['driver_options'][PDO::MYSQL_ATTR_INIT_COMMAND] = self::getMysqlInitCommand($config);
                unset($adapterParams['charset']); // Init command takes care of the charset
                break;
            case Mssql::class:
                $pdoType = $config->get('pdoType');
                if (empty($pdoType)) {
                    if (extension_loaded('sqlsrv')) {
                        $adapter = 'Sqlsrv';
                    } else {
                        $pdoType = 'dblib';
                    }
                }
                if ($pdoType === 'dblib') {
                    // Driver does not support setting attributes
                    unset($adapterParams['options']);
                    unset($adapterParams['driver_options']);
                }
                if (! empty($pdoType)) {
                    $adapterParams['pdoType'] = $pdoType;
                }
                break;
        }

        $db = new $adapter($adapterParams);
        $db->setFetchMode(Db::FETCH_OBJ);
        $db->getProfiler()->setEnabled(false);

        return $db;
    }

    protected static function getMysqlInitCommand(Settings $config)
    {
        /*
         * Set MySQL server SQL modes to behave as closely as possible to Oracle and PostgreSQL. Note that the
         * ONLY_FULL_GROUP_BY mode is left on purpose because MySQL requires you to specify all non-aggregate
         * columns in the group by list even if the query is grouped by the master table's primary key which is
         * valid ANSI SQL though. Further, in that case the query plan would suffer if you add more columns to
         * the group by list.
         */
        $charset = $config->get('charset');
        if (strlen($charset)) {
            $charset = trim($charset);
        } else {
            $charset = null;
        }
        $command =
            'SET SESSION SQL_MODE=\'STRICT_ALL_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,'
            . 'ANSI_QUOTES,PIPES_AS_CONCAT,NO_ENGINE_SUBSTITUTION\'';
        if ($charset !== null) {
            $command .= ", NAMES $charset";
            if ($charset === 'latin1') {
                // Required for MySQL 8+ because we need PIPES_AS_CONCAT and
                // have several columns with explicit COLLATE instructions
                $command .= ' COLLATE latin1_general_ci';
            }
        }

        $command .= ", time_zone='" . static::defaultTimezoneOffset() . "';";

        return $command;
    }

    protected static function getMainAdapterParams(Settings $config)
    {
        $generic = ['host', 'username', 'password', 'dbname', 'charset'];
        $params = [
            'options'        => self::DEFAULT_ADAPTER_OPTIONS,
            'driver_options' => self::DEFAULT_PDO_OPTIONS,
        ];
        foreach ($generic as $key) {
            $value = $config->get($key);
            if (strlen($value) > 0) {
                $params[$key] = $value;
            }
        }

        $port = $config->get('port');
        if ($port === null) {
            $port = static::getDefaultPort(static::getDbType($config));
        }
        if ($port !== null) {
            $params['port'] = $port;
        }

        return $params;
    }

    protected static function getAdapterClass(Settings $config)
    {
        $dbType = static::getDbType($config);
        if (array_key_exists($dbType, self::DB_ADAPTERS)) {
            return self::DB_ADAPTERS[$dbType];
        }

        throw new RuntimeException(
            'Backend "%s" is not supported',
            $config->get('db', 'mysql')
        );
    }

    protected static function getDefaultPort($dbType)
    {
        if (array_key_exists($dbType, self::DEFAULT_PORTS)) {
            return self::DEFAULT_PORTS[$dbType];
        }

        return null;
    }

    protected static function getMySqlSslParams(Settings $config)
    {
        $params = [];
        if ($config->get('use_ssl')) {
            if (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
                $parameterMap['ssl_do_not_verify_server_cert'] = PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT;
            }
            $parameterMap = [
                'ssl_key'    => PDO::MYSQL_ATTR_SSL_KEY,
                'ssl_cert'   => PDO::MYSQL_ATTR_SSL_CERT,
                'ssl_ca'     => PDO::MYSQL_ATTR_SSL_CA,
                'ssl_capath' => PDO::MYSQL_ATTR_SSL_CAPATH,
                'ssl_cipher' => PDO::MYSQL_ATTR_SSL_CIPHER,
            ];

            foreach ($parameterMap as $key => $option) {
                $value = $config->get($key);
                if (strlen($value)) {
                    $params[$option] = $value;
                }
            }
        }

        return $params;
    }

    /**
     * Get offset from the current default timezone to GMT
     *
     * @return string
     */
    protected static function defaultTimezoneOffset()
    {
        $tz = new DateTimeZone(date_default_timezone_get());
        $offset = $tz->getOffset(new DateTime());
        $prefix = $offset >= 0 ? '+' : '-';
        $offset = abs($offset);
        $hours = (int) floor($offset / 3600);
        $minutes = (int) floor(($offset % 3600) / 60);
        return sprintf('%s%d:%02d', $prefix, $hours, $minutes);
    }
}
