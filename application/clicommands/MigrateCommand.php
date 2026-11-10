<?php

namespace Icinga\Module\Eventtracker\Clicommands;

use Icinga\Module\Eventtracker\Config\IcingaResource;
use Icinga\Module\Eventtracker\Db\DbUtil;
use Icinga\Module\Eventtracker\Db\ZfDbConnectionFactory;

class MigrateCommand extends Command
{
    use CommandWithLoop;

    /**
     * Migrates source database to target database
     *
     * USAGE
     *
     * icingacli eventtracker migrate database
     *     --source
     *     --target
     */
    public function databaseAction()
    {
        $sourceName = $this->params->getRequired('source');
        $targetName = $this->params->getRequired('target');
        $sourceDb = ZfDbConnectionFactory::connection(IcingaResource::requireResourceConfig($sourceName));
        $targetDb = ZfDbConnectionFactory::connection(IcingaResource::requireResourceConfig($targetName));

        $tables = [
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
            "raw_event",
            "issue",
            "issue_activity",
            "file",
            "issue_file",
            "issue_history",
            "host_list",
            "host_list_member",
            "downtime_rule",
            "issue_downtime_history",
            "config_history",
            "action_history",
            "daemon_info",
            "eventtracker_schema_migration"
        ];
        if (!$this->params->get('force')) {
            foreach (["issue", "input"] as $table) {
                if ($targetDb->fetchOne(($targetDb->select()->from($table, "COUNT(*)"))) > 0) {
                    $this->fail("$table contains data. Please use --force if you really want to continue!");
                }
            }
        }
        foreach (array_reverse($tables) as $table) {
            $targetDb->delete($table);
        }
        foreach ($tables as $table) {
            $targetDb->beginTransaction();
            try {
                foreach ($sourceDb->fetchAll($sourceDb->select()->from($table)) as $row) {
                    $row = (array) $row;
                    foreach ($row as $k => $v) {
                        if ($k === 'uuid' || preg_match('/_uuid$/', $k) || preg_match('/_checksum$/', $k)) {
                            $row[$k] = DbUtil::quoteBinary($v, $targetDb);
                        }
                    }
                    $targetDb->insert($table, $row);
                }
                $targetDb->commit();
            } catch (\Exception $e) {
                try {
                    $targetDb->rollback();
                } catch (\Exception $e) {
                }
                throw $e;
            }
        }
    }
}
