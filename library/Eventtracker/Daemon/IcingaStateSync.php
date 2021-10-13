<?php

namespace Icinga\Module\Eventtracker\Daemon;

use Exception;
use gipfl\ZfDb\Adapter\Adapter as ZfDb;
use gipfl\ZfDb\Select as ZfDbSelect;
use RuntimeException;

class IcingaStateSync
{
    const TBL_CI = 'icinga_ci';
    const TBL_STATUS = 'icinga_ci_status';

    /** @var ZfDb */
    protected $db;

    /** @var IdoDb */
    protected $idoDb;

    public function __construct(ZfDb $db, IdoDb $idoDb)
    {
        $this->db = $db;
        $this->idoDb = $idoDb;
    }

    /**
     * @throws Exception
     */
    public function sync()
    {
        $existing = $this->fetchProblemStates();
        $new = [];
        $update = [];
        $all = [];
        $recovered = [];
        foreach ($this->fetchIdoProblems() as $problem) {
            $id = $problem->object_id;
            $all[$id] = $problem;
            if (isset($existing[$id])) {
                if ((int) $existing[$id] !== (int) $problem->severity) {
                    $update[$id] = $problem;
                    unset($update[$id]->object_id);
                }
            } else {
                $new[$id] = $problem;
            }
        }
        foreach ($existing as $id => $row) {
            if (! isset($all[$id])) {
                $recovered[] = $id;
            }
        }

        $stats = [];
        if (! empty($new)) {
            $this->transaction(function () use ($new) {
                $db = $this->db;
                foreach ($new as $row) {
                    $db->insert(self::TBL_STATUS, (array) $row);
                }
            });
            $stats[] = 'new=' . \count($new);
        }
        if (! empty($update)) {
            $this->transaction(function () use ($update) {
                $db = $this->db;
                foreach ($update as $id => $row) {
                    $db->update(self::TBL_STATUS, (array) $row, 'object_id = ' . (int) $id);
                }
            });
            $stats[] = 'changed=' . \count($update);
        }
        if (! empty($recovered)) {
            $this->db->delete(self::TBL_STATUS, $this->db->quoteInto(
                'object_id in (?)',
                $recovered
            ));
            $stats[] = 'recovered=' . \count($recovered);
        }

        if (! empty($stats)) {
            Logger::info('Status changes: ' . \implode(', ', $stats));
        }
    }

    protected function transaction($callback)
    {
        $db = $this->db;
        $db->beginTransaction();
        try {
            $callback();
            $db->commit();
        } catch (Exception $e) {
            try {
                $db->rollBack();
            } catch (Exception $e) {
                // ...well :D
            }

            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }
    }

    protected function fetchProblemStates()
    {
        return $this->db->fetchPairs(
            $this->db->select()->from(self::TBL_STATUS, ['object_id', 'severity'])
        );
    }

    protected function fetchIdoProblems()
    {
        return $this->idoDb->getDb()->fetchAll($this->prepareQuery());
    }

    protected function prepareQuery()
    {
        $db = $this->idoDb->getDb();

        return $db->select()->union([
            $this->selectHostStatusNok(),
            $this->selectServiceStatusNok()
        ], ZfDbSelect::SQL_UNION_ALL);
    }

    protected function selectHostStatusNok()
    {
        return $this
            ->selectHostStatus()
            ->where('hs.current_state != 0 OR hs.current_state IS NULL');
    }

    protected function selectHostStatus()
    {
        $db = $this->idoDb->getDb();
        $columns = [
            'object_id' => 'o.object_id',
            'severity'  => 'CASE
  WHEN hs.has_been_checked = 0 OR hs.has_been_checked IS NULL THEN 1
  WHEN hs.current_state = 0 THEN 0
  ELSE 128
  + CASE WHEN hs.scheduled_downtime_depth > 0 THEN 0 ELSE 64 END
  + CASE WHEN hs.problem_has_been_acknowledged = 1 THEN 0 ELSE 32 END
  + CASE WHEN hs.is_reachable = 1 THEN 16 ELSE 0 END
  + CASE hs.current_state
    WHEN 2 THEN 3
    WHEN 1 THEN 4
    ELSE 4
  END
END',
            'status' => "CASE
  WHEN hs.has_been_checked = 0 OR hs.has_been_checked IS NULL THEN 'pending'
  ELSE CASE hs.current_state
    WHEN 0 THEN 'ok'
    WHEN 1 THEN 'critical'
    WHEN 2 THEN 'unknown'
    ELSE 'critical'
  END
END",
            'is_problem'      => "CASE
  WHEN hs.has_been_checked = 0 OR hs.has_been_checked IS NULL OR hs.current_state = 0
  THEN 'n'
  ELSE 'y'
END",
            'is_pending'      => "CASE WHEN hs.has_been_checked = 0 OR hs.has_been_checked IS NULL"
                . " THEN 'y' ELSE 'n' END",
            'is_acknowledged' => "CASE WHEN hs.problem_has_been_acknowledged = 1 THEN 'y' ELSE 'n' END",
            'is_in_downtime'  => "CASE WHEN hs.scheduled_downtime_depth > 0 THEN 'y' ELSE 'n' END",
            'is_reachable'    => "CASE WHEN hs.is_reachable = 1 THEN 'y' ELSE 'n' END",
        ];
        return $db
            ->select()
            ->from(['hs' => 'icinga_hoststatus'], $columns)
            ->join(['o' => 'icinga_objects'], 'hs.host_object_id = o.object_id AND o.is_active = 1', []);
    }

    protected function selectServiceStatusNok()
    {
        return $this
            ->selectServiceStatus()
            ->where('ss.current_state != 0 OR ss.current_state IS NULL');
    }

    protected function selectServiceStatus()
    {
        $db = $this->idoDb->getDb();
        $columns = [
            'object_id' => 'o.object_id',
            'severity'  => 'CASE
  WHEN ss.has_been_checked = 0 OR ss.has_been_checked IS NULL THEN 1
  WHEN ss.current_state = 0 THEN 0
  ELSE 128
  + CASE WHEN ss.scheduled_downtime_depth > 0 THEN 0 ELSE 64 END
  + CASE WHEN ss.problem_has_been_acknowledged = 1 THEN 0 ELSE 32 END
  + CASE WHEN ss.is_reachable = 1 AND hs.current_state = 0 THEN 16 ELSE 0 END
  + CASE ss.current_state
    WHEN 3 THEN 3
    WHEN 2 THEN 4
    WHEN 1 THEN 2
    ELSE 4
  END
END',
            'status' => "CASE
  WHEN ss.has_been_checked = 0 OR ss.has_been_checked IS NULL THEN 'pending'
  ELSE CASE ss.current_state
    WHEN 0 THEN 'ok'
    WHEN 1 THEN 'warning'
    WHEN 2 THEN 'critical'
    WHEN 3 THEN 'unknown'
    ELSE 'critical'
  END
END",
            'is_problem'      => "CASE
  WHEN ss.has_been_checked = 0 OR ss.has_been_checked IS NULL OR ss.current_state = 0
  THEN 'n'
  ELSE 'y'
END",
            'is_pending'      => "CASE WHEN ss.has_been_checked = 0 OR ss.has_been_checked IS NULL"
                . " THEN 'y' ELSE 'n' END",
            'is_acknowledged' => "CASE WHEN ss.problem_has_been_acknowledged = 1 THEN 'y' ELSE 'n' END",
            'is_in_downtime'  => "CASE WHEN ss.scheduled_downtime_depth > 0 THEN 'y' ELSE 'n' END",
            'is_reachable'    => "CASE WHEN ss.is_reachable = 1 AND hs.current_state = 0 THEN 'y' ELSE 'n' END",
        ];

        return $db
            ->select()
            ->from(['ss' => 'icinga_servicestatus'], $columns)
            ->join(['o' => 'icinga_objects'], 'ss.service_object_id = o.object_id AND o.is_active = 1', [])
            ->join(['s' => 'icinga_services'], 'o.object_id = s.service_object_id', [])
            ->join(['h' => 'icinga_hosts'], 's.host_object_id = h.host_object_id', [])
            ->join(['hs' => 'icinga_hoststatus'], 'h.host_object_id = hs.host_object_id', []);
    }
}
