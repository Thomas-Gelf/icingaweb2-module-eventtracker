<?php

namespace Icinga\Module\Eventtracker\Clicommands;

use Exception;
use Icinga\Module\Eventtracker\Config\IcingaResource;
use Icinga\Module\Eventtracker\Db\ZfDbConnectionFactory;
use Icinga\Module\Eventtracker\MigrateDatabase;

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
     * @throws Exception
     */
    public function databaseAction()
    {
        $sourceName = $this->params->getRequired('source');
        $targetName = $this->params->getRequired('target');
        $sourceDb = ZfDbConnectionFactory::connection(IcingaResource::requireResourceConfig($sourceName));
        $targetDb = ZfDbConnectionFactory::connection(IcingaResource::requireResourceConfig($targetName));
        $migration = new MigrateDatabase($sourceDb, $targetDb);
        if (!$this->params->get('force')) {
            foreach (["issue", "input"] as $table) {
                if ($targetDb->fetchOne(($targetDb->select()->from($table, "COUNT(*)"))) > 0) {
                    $this->fail("$table contains data. Please use --force if you really want to continue!");
                }
            }
        }
        $migration->migrateConfigTables();
        $migration->migrateInitialHistoryTables();
    }

    /**
     * @throws Exception
     */
    public function historyAction()
    {
        $sourceName = $this->params->getRequired('source');
        $targetName = $this->params->getRequired('target');
        $sourceDb = ZfDbConnectionFactory::connection(IcingaResource::requireResourceConfig($sourceName));
        $targetDb = ZfDbConnectionFactory::connection(IcingaResource::requireResourceConfig($targetName));
        $migration = new MigrateDatabase($sourceDb, $targetDb);
        $migration->migrateHistoryTables();
    }
}
