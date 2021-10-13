<?php

namespace Icinga\Module\Eventtracker\Clicommands;

use gipfl\ZfDb\Adapter\Pdo\Mssql;
use Icinga\Cli\Command as CliCommand;
use Icinga\Module\Eventtracker\Config\IcingaResource;
use Icinga\Module\Eventtracker\Db\ZfDbConnectionFactory;

abstract class Command extends CliCommand
{
    /**
     * @param $name
     * @return Mssql
     */
    protected function requireMssqlResource($name)
    {
        $db = ZfDbConnectionFactory::connection(IcingaResource::requireResourceConfig($name));
        if (! $db instanceof Mssql) {
            // Well... it's ConfigurationError
            throw new \InvalidArgumentException("DB resource '$name' is not an MSSQL connection'");
        }

        return $db;
    }
}
