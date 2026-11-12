<?php

namespace Icinga\Module\Eventtracker;

use Exception;
use gipfl\DbMigration\Migration;
use gipfl\DbMigration\Migrations;
use gipfl\ZfDb\Adapter\Pdo\PdoAdapter;
use gipfl\ZfDb\Adapter\Pdo\Pgsql;
use gipfl\ZfDb\Expr;
use Icinga\Application\Icinga;
use Icinga\Application\Logger;
use Icinga\Module\Eventtracker\Db\DbUtil;
use Icinga\Application\Benchmark;
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
        foreach (array_keys(self::HISTORY_TABLES) as $table) {
            $this->targetDb->delete($table);
        }
        Benchmark::measure('Tables are empty, ready to go');
        foreach (self::CONFIG_TABLES as $table) {
            $this->targetDb->beginTransaction();
            try {
                $query = $this->sourceDb->select()->from($table);
                if ($table == "icinga_ci") {
                    $query->order("object_type");
                }
                foreach ($this->sourceDb->fetchAll($query) as $row) {
                    $row = $this->convertRow($row);
                    $this->insertOnConflict($table, $row);
                }
                $this->targetDb->commit();
                Benchmark::measure("$table migrated");
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
            if (! $this->hasRows($this->sourceDb, $table)) {
                continue;
            }
            $oldestTimestamp = $this->getOldestTimestamp($table, $column);
            while (true) {
                $newOldestTimestamp = $this->migrateBlock($table, $column, 20000, $oldestTimestamp);
                if ($newOldestTimestamp === $oldestTimestamp) {
                    break;
                }
                $oldestTimestamp = $newOldestTimestamp;
            }
        }
    }

    /**
     * @throws Exception
     */
    public function migrateInitialHistoryTables(): void
    {
        foreach (self::HISTORY_TABLES as $table => $column) {
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
                $query->where("$column <= ?", $begin);
            }
            $rows = $this->sourceDb->fetchAll($query);
            foreach ($rows as $row) {
                $row = $this->convertRow($row);
                $this->insertOnConflict($table, $row);
                $oldestTs = $row[$column];
            }
            $this->targetDb->commit();
            Benchmark::measure("$table migrated up to $limit rows");
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

        $ts =  $this->targetDb->fetchOne($this->targetDb->select()->from($table)->order("$column ASC")->limit(1));
        if (!$ts) {
            throw new RuntimeException("$table is empty");
        }
        return $ts;
    }

    private function convertRow($row)
    {
        $row = (array) $row;
        foreach ($row as $k => $v) {
            if ($k === 'uuid'
                || preg_match('/_uuid$/', $k)
                || $k === 'checksum'
                || preg_match('/_checksum$/', $k)
                || $k === 'data'
                || $k === 'message'
            ) {
                $row[$k] = DbUtil::quoteBinary($v, $this->targetDb);
            }
        }
        return $row;
    }

    private function hasRows(PdoAdapter $db, string $table): bool
    {
        return 1 === (int) $db->fetchOne($db->select()->from($table, "(1)")->limit(1));
    }

    public function ensureSchemaIsReady(): void
    {
        $migrations = new Migrations($this->targetDb, Icinga::app()->getModuleManager()->getModuleDir(
            'eventtracker',
            '/schema'
        ), 'eventtracker_schema_migration');

        if (! $migrations->hasSchema()) {
            if ($migrations->hasAnyTable()) {
                throw new RuntimeException('DB has no eventtracker schema, but other tables');
            }
            Logger::warning('DB has no eventtracker schema, creating now');
            $migration = new Migration(0, $migrations->loadMigrationFile(0));
            $migration->apply($this->targetDb);
            $startupSchemaVersion = $migrations->getLastMigrationNumber();
            Logger::info('Eventtracker schema has been created');
        }
        if ($migrations->hasPendingMigrations()) {
            Logger::warning('Schema is outdated, applying migrations');
            $count = $migrations->countPendingMigrations();
            $migrations->applyPendingMigrations();
            if ($count ===1) {
                Logger::info('A pending DB migration has been applied');
            } else {
                Logger::info("$count pending DB migrations have been applied");
            }
        }
    }

    // Due to possible duplicate timestamps we need this function to ignore duplicate keys on insert
    private function insertOnConflict($table, array $bind): int
    {
        $cols = [];
        $vals = [];
        foreach ($bind as $col => $val) {
            $cols[] = $this->targetDb->quoteIdentifier($col, true);
            if ($val instanceof Expr) {
                $vals[] = $val->__toString();
                unset($bind[$col]);
            } else {
                $vals[] = '?';
            }
        }

        // build the statement
        if ($this->targetDb instanceof Pgsql) {
            $sql = "INSERT INTO "
                . $this->targetDb->quoteIdentifier($table, true)
                . ' (' . implode(', ', $cols) . ') '
                . 'VALUES (' . implode(', ', $vals) . ')';
        } else {
            $sql = "INSERT IGNORE INTO "
                . $this->targetDb->quoteIdentifier($table, true)
                . ' (' . implode(', ', $cols) . ') '
                . 'VALUES (' . implode(', ', $vals) . ')';
        }

        // execute the statement and return the number of affected rows
        $bind = array_values($bind);
        $stmt = $this->targetDb->query($sql, $bind);
        return $stmt->rowCount();
    }
}
