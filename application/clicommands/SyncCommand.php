<?php

namespace Icinga\Module\Eventtracker\Clicommands;

use gipfl\Protocol\JsonRpc\Connection;
use gipfl\Protocol\NetString\StreamWrapper;
use Icinga\Module\Eventtracker\Daemon\IcingaCiSync;
use Icinga\Module\Eventtracker\Daemon\IcingaStateSync;
use Icinga\Module\Eventtracker\Daemon\IdoDb;
use Icinga\Module\Eventtracker\Daemon\JsonRpcLogWriter as JsonRpcLogWriterAlias;
use Icinga\Module\Eventtracker\Daemon\Logger;
use Icinga\Module\Eventtracker\DbFactory;
use Icinga\Module\Eventtracker\Issue;
use Icinga\Module\Eventtracker\Issues;
use Icinga\Module\Eventtracker\Scom\ScomSync;
use React\EventLoop\Factory as Loop;
use React\EventLoop\LoopInterface;
use React\Promise\ExtendedPromiseInterface;
use React\Stream\ReadableResourceStream;
use React\Stream\WritableResourceStream;
use function React\Promise\resolve;

class SyncCommand extends Command
{
    /** @var LoopInterface */
    protected $loop;

    public function init()
    {
        $this->app->getModuleManager()->loadEnabledModules();
        $loop = $this->loop = Loop::create();
        if ($this->params->get('rpc')) {
            $this->enableRpc($loop);
        }
    }

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
            Logger::info('No SCOM sync has been configured');
        }
    }

    /**
     * @return \React\Promise\FulfilledPromise
     * @throws \Icinga\Exception\ConfigurationError
     */
    public function runIdo()
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

        return resolve();
    }

    /**
     * @return \React\Promise\FulfilledPromise
     * @throws \Icinga\Exception\ConfigurationError
     */
    public function runIdoState()
    {
        $ido = IdoDb::fromMonitoringModule();
        $sync = new IcingaStateSync(DbFactory::db(), $ido);
        $sync->sync();

        return resolve();
    }

    public function runExpirations()
    {
        $db = DbFactory::db();
        $issues = new Issues($db);
        $expired = $issues->fetchExpiredUuids();
        foreach ($expired as $uuid) {
            Issue::expireUuid($uuid, $db);
        }
        $count = count($expired);
        if ($count > 0) {
            Logger::info(sprintf('Expired %d outdated issues', $count));
        }

        return resolve();
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

    public function failNice($msg)
    {
        if ($this->isRpc()) {
            Logger::error($msg);
        } else {
            \printf("%s: %s\n", $this->screen->colorize('ERROR', 'red'), $msg);
        }

        $this->loop->futureTick(function () {
            $this->loop->stop();
            exit(1);
        });
    }

    protected function isRpc()
    {
        return (bool) $this->params->get('rpc');
    }

    protected function runWithLoop($callable)
    {
        $this->loop->futureTick(function () use ($callable) {
            try {
                $result = $callable();
                if ($result instanceof ExtendedPromiseInterface) {
                    $result->then(function () {
                        $this->loop->stop();
                    }, function ($error) {
                        if ($error instanceof \Exception) {
                            $this->failNice($error->getMessage());
                        } else {
                            $this->failNice($error);
                        }
                    });
                } else {
                    $this->loop->stop();
                }
            } catch (\Exception $e) {
                $this->failNice($e->getMessage());
            }
        });
        $this->loop->run();
    }

    protected function enableRpc(LoopInterface $loop)
    {
        $netString = new StreamWrapper(
            new ReadableResourceStream(STDIN, $loop),
            new WritableResourceStream(STDOUT, $loop)
        );
        $jsonRpc = new Connection();
        $jsonRpc->handle($netString);

        Logger::replaceRunningInstance(new JsonRpcLogWriterAlias($jsonRpc));
    }
}
