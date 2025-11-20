<?php

namespace Icinga\Module\Eventtracker;

use gipfl\Json\JsonString;
use gipfl\ZfDb\Adapter\Adapter as Db;
use gipfl\ZfDb\Select;
use Icinga\Application\Config;
use stdClass;

class IcingaCi
{
    protected static string $tableName = 'icinga_ci';
    protected static string $tableNameVars = 'icinga_ci_var';

    public static function exists(Db $db, string $hostname, ?string $service = null): bool
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

    public static function loadOptional(Db $db, $hostname, $service = null): ?stdClass
    {
        if ($hostname === null) {
            return null;
        }
        $object = static::fetchOptionalCi($db, $hostname, $service);
        if ($object) {
            return $object;
        } else {
            $domain = Config::module('eventtracker')->get('ido-sync', 'search_domain');
            if ($domain) {
                $domain = \trim($domain, '.');
                return static::fetchOptionalCi($db, "$hostname.$domain", $service);
            }
        }

        return null;
    }

    public static function loadOptionalForIssue(Db $db, Issue $issue): ?stdClass
    {
        $hostname = $issue->get('host_name');
        $service = $issue->get('object_name');
        $object = static::loadOptional($db, $hostname, $service);
        if ($object) {
            return $object;
        } elseif ($service === null) {
            return null;
        } else {
            return static::loadOptional($db, $hostname);
        }
    }

    protected static function fetchOptionalCi(Db $db, $hostname, $service = null): ?stdClass
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

    protected static function fetchCi(Db $db, $id): ?stdClass
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

    protected static function prepareCiQuery(Db $db): Select
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

    protected static function fetchCiVars(Db $db, $id): stdClass
    {
        $query = $db->select()->from(self::$tableNameVars, [
            'varname',
            'varvalue',
            'varformat',
        ])->where('object_id = ?', $id);

        $vars = [];
        foreach ($db->fetchAll($query) as $var) {
            if ($var->varformat === 'json') {
                $vars[$var->varname] = JsonString::decode($var->varvalue);
            } else {
                $vars[$var->varname] = (string) $var->varvalue;
            }
        }

        return (object) $vars;
    }
}
