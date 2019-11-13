<?php

namespace Icinga\Module\Eventtracker\Clicommands;

use Icinga\Cli\Command;
use Icinga\Data\Db\DbConnection;
use Icinga\Module\Eventtracker\DbFactory;
use Icinga\Module\Eventtracker\Scom\ScomSync;
use Zend_Db_Adapter_Pdo_Mssql as Mssql;

class SyncCommand extends Command
{
    public function scomAction()
    {
        $scom = new ScomSync(DbFactory::db());
        if ($filename = $this->params->get('json')) {
            $scom->syncFromPlainObjects($this->readJsonFile($filename));
        } elseif ($resource = $this->params->get('db-resource')) {
            $scom->syncFromDb($this->requireMssqlResource($resource));
        } elseif ($filename = $this->Config()->get('scom', 'simulation_file')) {
            $scom->syncFromPlainObjects($this->readJsonFile($filename));
        } elseif ($resource = $this->Config()->get('scom', 'db_resource')) {
            $scom->syncFromDb($this->requireMssqlResource($resource));
        } else {
            $this->failNice('Either --db-resource, --json or a config setting is required');
        }
    }

    protected function readJsonFile($filename)
    {
        if (substr($filename, 0, 1) !== '/') {
            $basedir = dirname(dirname(__DIR__));
            $filename = "$basedir/$filename";
        }

        $content = json_decode(file_get_contents($filename));
        if (! $content) {
            $this->failNice("Failed to read JSON form $filename");
        }

        return $content;
    }

    /**
     * @param $name
     * @return Mssql
     */
    protected function requireMssqlResource($name)
    {
        $db  = null;
        try {
            $db = DbConnection::fromResourceName($name)->getDbAdapter();
            if (! $db instanceof Mssql) {
                $this->failNice("DB resource '$name' is not an MSSQL connection'");
            }
        } catch (\Exception $e) {
            $this->failNice($e->getMessage());
        }

        return $db;
    }

    public function failNice($msg)
    {
        printf("%s: %s\n", $this->screen->colorize('ERROR', 'red'), $msg);
        exit(1);
    }
}
