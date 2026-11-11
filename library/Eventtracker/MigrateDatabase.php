<?php

namespace Icinga\Module\Eventtracker;

use Exception;
use gipfl\ZfDb\Adapter\Pdo\PdoAdapter;
use Icinga\Module\Eventtracker\Db\DbUtil;
use RuntimeException;

class MigrateDatabase
{
    private PdoAdapter $sourceDb;
    private PdoAdapter $targetDb;
    private const CONFIG_TABLES = [
        "object_class",
        "sender",
        "input",
        "bucket",
        "channel",
        "api_token",
        "action",
        "map",
        "problem_handling",
        "icinga_ci",
        "icinga_ci_status",
        "icinga_ci_var",
        "issue",
        "issue_activity",
        "file",
        "issue_file",
        "host_list",
        "host_list_member",
        "downtime_rule",
        "daemon_info",
        "eventtracker_schema_migration",
    ];
    private const HISTORY_TABLES = [
        "action_history"         => "ts_done",
        "config_history"         => "ts_modification",
        "issue_downtime_history" => "ts_modification",
        "issue_history"          => "ts_first_event",
        "raw_event"              => "ts_received",
    ];
    public function __construct(PdoAdapter $sourceDb, PdoAdapter $targetDb)
    {
        $this->sourceDb = $sourceDb;
        $this->targetDb = $targetDb;
    }
    public function migrateConfigTables(): void
    {
        foreach (array_reverse(self::CONFIG_TABLES) as $table) {
            $this->targetDb->delete($table);
        }
        foreach (self::CONFIG_TABLES as $table) {
            $this->targetDb->beginTransaction();
            try {
                $query = $this->sourceDb->select()->from($table);
                if ($table == "icinga_ci") {
                    $query->order("object_type");
                }
                foreach ($this->sourceDb->fetchAll($query) as $row) {
                    $row = (array) $row;
                    foreach ($row as $k => $v) {
                        if ($k === 'uuid' || preg_match('/_uuid$/', $k) || preg_match('/_checksum$/', $k)) {
                            $row[$k] = DbUtil::quoteBinary($v, $this->targetDb);
                        }
                    }
                    $this->targetDb->insert($table, $row);
                }
                $this->targetDb->commit();
            } catch (Exception $e) {
                try {
                    $this->targetDb->rollback();
                } catch (Exception $e) {
                }
                throw $e;
            }
        }
    }

    public function migrateHistoryTables(): void
    {
        foreach (self::HISTORY_TABLES as $table => $column) {
            $oldestTimestamp = $this->getOldestTimestamp($table, "ts_first_event");
            while (true) {
                $newOldestTimestamp = $this->migrateBlock($table, $column, 100000, $oldestTimestamp);
                if ($newOldestTimestamp === $oldestTimestamp) {
                    break;
                }
            }
        }
    }

    /**
     * @throws Exception
     */
    public function migrateInitialHistoryTables(): void
    {
        $tables = [
            "action_history" => "ts_done",
            "config_history" => "ts_modification",
            "issue_downtime_history" => "ts_modification",
            "issue_history" => "ts_first_event",
            "raw_event" => "ts_received"
        ];
        foreach ($tables as $table => $column) {
            $this->migrateBlock($table, $column, 1000);
        }
    }

    private function migrateBlock(string $table, string $column, int $limit, ?int $begin = null): ?int
    {
        $this->targetDb->beginTransaction();
        $oldestTs = $begin;
        try {
            $query = $this->sourceDb->select()->from($table)->order("$column DESC")->limit($limit);
            if ($begin !== null) {
                $query->where("$column < ?", $begin);
            }
            $rows = $this->sourceDb->fetchAll($query);
            foreach ($rows as $row) {
                $this->targetDb->insert($table, $row);
                $oldestTs = $row->$column;
            }
            $this->targetDb->commit();
        } catch (Exception $e) {
            try {
                $this->targetDb->rollback();
            } catch (Exception $e) {
            }
            throw $e;
        }

        return $oldestTs;
    }

    private function getOldestTimestamp(string $table, string $column): int
    {

        $ts =  $this->targetDb->fetchOne($this->targetDb->select()->from($table)->order("$column DESC")->limit(1));
        if (!$ts) {
            throw new RuntimeException("$table is empty");
        }
        return $ts;
    }
}
