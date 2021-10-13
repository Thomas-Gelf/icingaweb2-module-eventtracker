<?php

namespace Icinga\Module\Eventtracker;

use gipfl\ZfDb\Adapter\Adapter as Db;
use Icinga\Exception\NotFoundError;

class Issues
{
    protected $db;

    public function __construct(Db $db)
    {
        $this->db = $db;
    }

    /**
     * @return Issue[]
     * @throws NotFoundError
     */
    public function fetchExpiredUuids()
    {
        $select = $this->db->select()
            ->from('issue', 'issue_uuid')
            ->where('ts_expiration < ?', time() * 1000);

        return $this->db->fetchCol($select);
    }

    /**
     * @param $uuids
     * @return array
     * @throws NotFoundError
     */
    public function fetchByUuids($uuids)
    {
        $issues = [];
        foreach ($uuids as $uuid) {
            $issues[] = Issue::load($uuid, $this->db);
        }

        return $issues;
    }

    /**
     * @param $host
     * @param null $object
     * @return Issue[]
     * @throws NotFoundError
     */
    public function fetchFor($host, $object = null)
    {
        $select = $this->db->select()
            ->from('issue', 'issue_uuid')
            ->where('host_name = ?', $host);
        if ($object === null) {
            $select->where('object_name IS NULL');
        } elseif ($object !== '*') {
            $select->where('object_name = ?', $object);
        }

        return $this->fetchByUuids($this->db->fetchCol($select));
    }
}
