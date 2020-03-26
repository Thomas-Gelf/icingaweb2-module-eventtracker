<?php

namespace Icinga\Module\Eventtracker\Clicommands;

use Icinga\Cli\Command as CliCommand;
use Icinga\Data\Db\DbConnection;
use Zend_Db_Adapter_Pdo_Mssql as Mssql;

abstract class Command extends CliCommand
{
    /**
     * @param $name
     * @return Mssql
     */
    protected function requireMssqlResource($name)
    {
        $db  = null;
        $db = DbConnection::fromResourceName($name)->getDbAdapter();
        if (! $db instanceof Mssql) {
            // Well... it's ConfigurationError
            throw new \InvalidArgumentException("DB resource '$name' is not an MSSQL connection'");
        }

        return $db;
    }
}
