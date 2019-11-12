<?php

namespace Icinga\Module\Eventtracker\Daemon;

use Icinga\Data\ResourceFactory;
use Icinga\Module\Monitoring\Backend\MonitoringBackend;
use Zend_Db_Adapter_Abstract as DbAdapter;
use Zend_Db_Select as DbSelect;

/**
 * Class IdoDb
 *
 * Small IDO abstraction layer
 */
class IdoDb
{
    /** @var DbAdapter */
    protected $db;

    /** @var string */
    protected $lastHostsChecksum;

    /**
     * IdoDb constructor.
     * @param DbAdapter $db
     */
    public function __construct(DbAdapter $db)
    {
        $this->db = $db;
    }

    /**
     * @return DbAdapter
     */
    public function getDb()
    {
        return $this->db;
    }

    public function coreIsRunning()
    {
        $query = $this->db
            ->select()
            ->from(['ps' => 'icinga_programstatus'], 'COUNT(*)')
            ->where('is_currently_running = ?', 1)
            ->where('config_dump_in_progress = ?', 0)
        ;

        return (int) $this->db->fetchOne($query) > 0;
    }

    public function fetchHostsWithVars(array $varNames)
    {
        $hosts = [];
        $checkSums = '';
        foreach ($this->db->fetchAll($this->selectHosts()) as $row) {
            $row->vars = (object) [];
            $hosts[$row->object_id] = $row;
        }

        foreach ($this->fetchAllHostVarsByName($varNames) as $row) {
            if (! isset($hosts[$row->object_id])) {
                echo "Skipping:\n";
                print_r($row);
                continue;
            }
            $host = $hosts[$row->object_id];
            // TODO: json_decode?
            if ($row->is_json) {
                $value = \json_decode($row->varvalue);
            } else {
                $value = $row->varvalue;
            }
            $host->vars->{$row->varname} = $value;
        }

        foreach ($hosts as $host) {
            $host->checksum = \sha1(\json_encode($host), true);
            $checkSums .= $host->checksum;
        }
        $this->lastHostsChecksum = \sha1($checkSums, true);

        return $hosts;
    }

    public function getLastHostsChecksum()
    {
        return $this->lastHostsChecksum;
    }

    protected function selectHosts()
    {
        return $this->db
            ->select()
            ->from(['ho' => 'icinga_objects'], [
                'object_id'       => 'ho.object_id',
                'host_id'         => '(NULL)',
                'object_type'     => "('host')",
                'host_name'       => 'ho.name1',
                'service_name'    => '(NULL)',
                'display_name'    => 'h.display_name',
            ])
            ->join(['h' => 'icinga_hosts'], 'ho.object_id = h.host_object_id', [])
            ->where('ho.is_active = 1')
            // ->where('ho.objecttype_id = 1')
            ->order('ho.name1');
    }

    protected function selectServices()
    {
        return $this->db->select()->from(
            ['so' => 'icinga_objects'],
            [
                'object_id'       => 'so.object_id',
                'object_type'     => "('service')",
                'host_id'         => 'hs.host_object_id',
                'host_name'       => 'so.name1',
                'service_name'    => 'so.name2',
            ]
        )->order('so.name1')
        ->order('so.name2');
    }

    /**
     * @param DbSelect $select
     * @return DbSelect
     * @throws \Zend_Db_Select_Exception
     */
    protected function addServiceStatus(DbSelect $select)
    {
        return $select->columns([
            'state_type'      => "(CASE WHEN ss.state_type = 1 THEN 'HARD' ELSE 'SOFT' END)",
            'state'           => '(CASE ss.current_state'
                . " WHEN 0 THEN 'OK'"
                . " WHEN 1 THEN 'WARNING'"
                . " WHEN 2 THEN 'CRITICAL'"
                . " ELSE 'UNKNOWN'"
                . " END)",
            'hard_state'      => 'CASE WHEN ss.has_been_checked = 0 OR ss.has_been_checked IS NULL THEN 99'
                . ' ELSE CASE WHEN ss.state_type = 1 THEN ss.current_state'
                . ' ELSE ss.last_hard_state END END',
            'is_acknowledged' => 'ss.problem_has_been_acknowledged',
            'is_in_downtime'  => 'CASE WHEN (ss.scheduled_downtime_depth = 0) THEN 0 ELSE 1 END',
            'output'          => 'ss.output',
        ])->join(
            ['ss' => 'icinga_servicestatus'],
            'so.object_id = ss.service_object_id AND so.is_active = 1',
            []
        );
    }

    protected function addHostStatus(DbSelect $select)
    {
        return $select->columns([
            'state_type'      => "(CASE WHEN hs.state_type = 1 THEN 'HARD' ELSE 'SOFT' END)",
            'state'           => '(CASE hs.current_state'
                . " WHEN 0 THEN 'UP'"
                . " WHEN 2 THEN 'UNREACHABLE'"
                . " ELSE 'DOWN'"
                . " END)",
            'hard_state'      => 'CASE WHEN hs.has_been_checked = 0 OR hs.has_been_checked IS NULL THEN 99'
                . ' ELSE CASE WHEN hs.state_type = 1 THEN hs.current_state'
                . ' ELSE hs.last_hard_state END END',
            'is_acknowledged' => 'hs.problem_has_been_acknowledged',
            'is_in_downtime'  => 'CASE WHEN (hs.scheduled_downtime_depth = 0) THEN 0 ELSE 1 END',
            'output'          => 'hs.output',
        ])->join(
            ['s' => 'icinga_services'],
            's.service_object_id = ss.service_object_id',
            []
        );
    }

    public function fetchAllHostVarsByName(array $varNames)
    {
        return $this->fetchAllObjectTypeVarsByName(1, $varNames);
    }

    public function fetchAllServiceVarsByName(array $varNames)
    {
        return $this->fetchAllObjectTypeVarsByName(2, $varNames);
    }

    public function fetchAllObjectTypeVarsByName($objectTypeId, array $varNames)
    {
        if (empty($varNames)) {
            return [];
        }

        $query = $this->db->select()
            ->from(['cv' => 'icinga_customvariablestatus'], [
                'o.object_id',
                'cv.varname',
                'cv.varvalue',
                'cv.is_json',
            ])
            ->join(
                ['o' => 'icinga_objects'],
                'o.object_id = cv.object_id',
                []
            )
            ->where('o.objecttype_id = 1')
            ->where('o.is_active = 1')
            ->where('cv.varname IN (?)', $varNames)
            ->order('o.name1')
            ->order('o.name2')
            ->order('cv.varname');

        return $this->db->fetchAll($query);
    }

    protected function enrichRowWithVars($row)
    {
        if ($row->object_type === 'host') {
             $this->enrichWithVars($row, $row->id, 'host.vars.');
        } else {
            $this->enrichWithVars($row, $row->host_id, 'host.vars.');
            $this->enrichWithVars($row, $row->id, 'service.vars.');
        }

        return $row;
    }

    protected function enrichWithVars($row, $objectId, $prefix)
    {
        $query = $this->db->select()->from(
            ['cv' => 'icinga_customvariablestatus'],
            ['cv.varname', 'cv.varvalue']
        )->where('object_id = ?', $objectId);

        foreach ($this->db->fetchPairs($query) as $key => $value) {
            $row->{"$prefix$key"} = $value;
        }

        return $row;
    }

    protected function enrichRowsWithVars($rows)
    {
        if (empty($rows)) {
            return;
        }

        $serviceHostIds = [];
        foreach ($rows as $row) {
            if ($row->host_id) {
                if (! array_key_exists($row->host_id, $serviceHostIds)) {
                    $serviceHostIds[$row->host_id] = [];
                }
                $serviceHostIds[$row->host_id][] = $row->id;
            }
        }

        $query = $this->db->select()->from(
            ['cv' => 'icinga_customvariablestatus'],
            ['cv.object_id', 'cv.varname', 'cv.varvalue']
        )->where('object_id IN (?)', array_keys($rows));

        foreach ($this->db->fetchAll($query) as $row) {
            $key = $rows[$row->object_id]->service_name === null
                ? 'host.vars.' . $row->varname
                : 'service.vars.' . $row->varname;

            $rows[$row->object_id]->$key = $row->varvalue;
        }

        if (empty($serviceHostIds)) {
            return;
        }

        $query = $this->db->select()->from(
            ['cv' => 'icinga_customvariablestatus'],
            ['cv.object_id', 'cv.varname', 'cv.varvalue']
        )->where('object_id IN (?)', array_keys($serviceHostIds));

        foreach ($this->db->fetchAll($query) as $row) {
            $key = 'host.vars.' . $row->varname;

            foreach ($serviceHostIds[$row->object_id] as $id) {
                $rows[$id]->$key = $row->varvalue;
            }
        }
    }

    /**
     * Instantiate with a given Icinga Web 2 resource name
     *
     * @param $name
     * @return static
     */
    public static function fromResourceName($name)
    {
        return new static(
            ResourceFactory::create($name)->getDbAdapter()
        );
    }

    /**
     * Borrow the database connection from the monitoring module
     *
     * @return static
     * @throws \Icinga\Exception\ConfigurationError
     */
    public static function fromMonitoringModule()
    {
        return new static(
            MonitoringBackend::instance()->getResource()->getDbAdapter()
        );
    }

    // Unused, might help to replace BEM
    protected function XXfetchProblemHosts()
    {
        return $this->db->fetchAll(
            $this->selectHosts()
                ->where('hs.current_state > 0')
                ->where('hs.state_type = 1')
                ->where('hs.scheduled_downtime_depth = 0')
                ->where('hs.problem_has_been_acknowledged = 0')
        );
    }

    protected function XXfetchProblemServices()
    {
        return $this->db->fetchAll(
            $this->selectServices()
                ->where('hs.current_state = 0')
                ->where('ss.state_type = 1')
                ->where('ss.current_state > 0')
                ->where('ss.scheduled_downtime_depth = 0')
                ->where('ss.problem_has_been_acknowledged = 0')
        );
    }
}
