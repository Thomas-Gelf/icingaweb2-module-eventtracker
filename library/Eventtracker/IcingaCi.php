<?php

namespace Icinga\Module\Eventtracker;

use Icinga\Application\Config;
use Zend_Db_Adapter_Abstract as Db;

class IcingaCi
{
    protected static $tableName = 'icinga_ci';

    protected static $tableNameVars = 'icinga_ci_var';

    /**
     * @param Db $db
     * @param string $hostname
     * @param string|null $service
     * @return bool
     */
    public static function exists(Db $db, $hostname, $service = null)
    {
        $query = $db->select()
            ->from(self::$tableName, 'COUNT(*)')
            ->where('host_name = ?', $hostname);
        if ($service === null) {
            $query->where('object_type = ?', 'host');
        } else {
            $query->where('service_name = ?', $service);
        }
        return $db->fetchOne($query) > 0;
    }

    public static function eventuallyLoad(Db $db, $hostname, $service = null)
    {
        if ($hostname === null) {
            return null;
        }
        $object = static::eventuallyFetchCi($db, $hostname, $service);
        if ($object) {
            return $object;
        } else {
            $domain = \trim(Config::module('eventtracker')->get('ido-sync', 'search_domain'), '.');
            if ($domain) {
                return static::eventuallyFetchCi($db, "$hostname.$domain", $service);
            }
        }

        return null;
    }

    public static function eventuallyLoadForIssue(Db $db, Issue $issue)
    {
        $hostname = $issue->get('host_name');
        $service = $issue->get('object_name');
        $object = static::eventuallyLoad($db, $hostname, $service);
        if ($object) {
            return $object;
        } elseif ($service === null) {
            return null;
        } else {
            return static::eventuallyLoad($db, $hostname);
        }
    }

    protected static function eventuallyFetchCi(Db $db, $hostname, $service = null)
    {
        $query = self::prepareCiQuery($db)->where('host_name = ?', $hostname);

        if ($service === null) {
            $query->where('object_type = ?', 'host');
        } else {
            $query->where('service_name = ?', $service);
        }

        $ci = $db->fetchRow($query);
        if ($ci) {
            $ci->vars = static::fetchCiVars($db, $ci->object_id);
            return $ci;
        } else {
            return null;
        }
    }

    protected static function fetchCi(Db $db, $id)
    {
        $query = self::prepareCiQuery($db)->where('object_id = ?', $id);

        $ci = $db->fetchRow($query);
        if ($ci) {
            $ci->vars = static::fetchCiVars($db, $ci->object_id);
            return $ci;
        } else {
            return null;
        }
    }

    protected static function prepareCiQuery(Db $db)
    {
        return $db->select()->from(self::$tableName, [
            'object_id',
            'host_id',
            'object_type',
            // 'checksum',
            'host_name',
            'service_name',
            'display_name',
        ]);
    }

    protected static function fetchCiVars(Db $db, $id)
    {
        $query = $db->select()->from(self::$tableNameVars, [
            'varname',
            'varvalue',
            'varformat',
        ])->where('object_id = ?', $id);

        $vars = [];
        foreach ($db->fetchAll($query) as $var) {
            if ($var->varformat === 'json') {
                $vars[$var->varname] = \json_decode($var->varvalue);
            } else {
                $vars[$var->varname] = (string) $var->varvalue;
            }
        }

        return (object) $vars;
    }
}
