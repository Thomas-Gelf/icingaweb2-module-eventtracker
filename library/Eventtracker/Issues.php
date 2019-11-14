<?php

namespace Icinga\Module\Eventtracker;

use Icinga\Exception\NotFoundError;
use Zend_Db_Adapter_Abstract as Db;

class Issues
{
    protected $db;

    public function __construct(Db $db)
    {
        $this->db = $db;
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

        $issues = [];
        $uuids = $this->db->fetchCol($select);
        foreach ($uuids as $uuid) {
            $issues[] = Issue::load($uuid, $this->db);
        }

        return $issues;
    }
}
