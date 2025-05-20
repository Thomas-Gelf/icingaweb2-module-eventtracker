<?php

namespace Icinga\Module\Eventtracker\Clicommands;

use Icinga\Module\Eventtracker\Daemon\IcingaCiSync;
use Icinga\Module\Eventtracker\Daemon\IcingaStateSync;
use Icinga\Module\Eventtracker\Daemon\IdoDb;
use Icinga\Module\Eventtracker\DbFactory;
use Icinga\Module\Eventtracker\Issue;
use Icinga\Module\Eventtracker\Issues;
use Icinga\Module\Eventtracker\Scom\ScomSync;
use React\Promise\ExtendedPromiseInterface;

use function React\Promise\resolve;

class SyncCommand extends Command
{
    use CommandWithLoop;

    /**
     * Daemon/JobRunner is running this action
     */
    public function scomAction()
    {
        $this->runWithLoop(function () {
            $this->runScom();
        });
    }

    /**
     * Daemon/JobRunner is running this action
     */
    public function idoAction()
    {
        $this->runWithLoop(function () {
            $this->runIdo();
        });
    }

    /**
     * Daemon/JobRunner is running this action
     */
    public function idostateAction()
    {
        $this->runWithLoop(function () {
            $this->runIdoState();
        });
    }

    /**
     * Daemon/JobRunner is running this action
     */
    public function expireAction()
    {
        $this->runWithLoop(function () {
            $this->runExpirations();
        });
    }

    /**
     * Daemon/JobRunner is running this action
     */
    public function hostlistAction()
    {
        $this->runWithLoop(function () {
            $this->runHostlists();
        });
    }

    protected function runScom()
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
        } elseif (! $this->isRpc()) {
            throw new \InvalidArgumentException(
                'Either --db-resource, --json or a config setting is required'
            );
        } else {
            $this->logger->info('No SCOM sync has been configured');
        }
    }

    /**
     * @return \React\Promise\FulfilledPromise
     * @throws \Icinga\Exception\ConfigurationError
     */
    public function runIdo(): ExtendedPromiseInterface
    {
        $ido = IdoDb::fromMonitoringModule();
        $sync = new IcingaCiSync(DbFactory::db(), $ido);
        $vars = $this->Config()->get('ido-sync', 'vars');
        if (\strlen($vars)) {
            $vars = \preg_split('/\s*,\s*/', $vars, -1, PREG_SPLIT_NO_EMPTY);
            if (! empty($vars)) {
                $sync->setCustomVarNames($vars);
            }
        }
        $sync->sync();

        return resolve(null);
    }

    /**
     * @return \React\Promise\FulfilledPromise
     * @throws \Icinga\Exception\ConfigurationError
     */
    public function runIdoState(): ExtendedPromiseInterface
    {
        $ido = IdoDb::fromMonitoringModule();
        $sync = new IcingaStateSync(DbFactory::db(), $ido);
        $sync->sync();

        return resolve(null);
    }

    public function runExpirations(): ExtendedPromiseInterface
    {
        $db = DbFactory::db();
        $issues = new Issues($db);
        $expired = $issues->fetchExpiredUuids();
        foreach ($expired as $uuid) {
            Issue::expireUuid($uuid, $db);
        }
        $count = count($expired);
        if ($count > 0) {
            $this->logger->info(sprintf('Expired %d outdated issues', $count));
        }

        return resolve(null);
    }

    public function runHostlists(): ExtendedPromiseInterface
    {
        $configured = $this->Config()->getSection('director-host-lists')->toArray();
        if (empty($configured)) {
            return resolve(null);
        }
        $db = DbFactory::db();

        return resolve(null);
    }

    protected function readJsonFile($filename)
    {
        if (substr($filename, 0, 1) !== '/') {
            $basedir = dirname(__DIR__, 2);
            $filename = "$basedir/$filename";
        }

        $content = json_decode(file_get_contents($filename));
        if (! $content) {
            $this->failNice("Failed to read JSON form $filename");
        }

        return $content;
    }
}
