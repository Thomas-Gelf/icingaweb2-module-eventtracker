<?php

namespace Icinga\Module\Eventtracker;

use Zend_Db_Adapter_Abstract as Db;

class IcingaCi
{
    protected static $tableName = 'icinga_ci';

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
}
