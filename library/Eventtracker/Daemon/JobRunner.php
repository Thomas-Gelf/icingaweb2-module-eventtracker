<?php

namespace Icinga\Module\Eventtracker\Daemon;

use gipfl\Process\ProcessKiller;
use gipfl\Process\ProcessList;
use gipfl\Protocol\JsonRpc\Handler\NamespacedPacketHandler;
use gipfl\Protocol\JsonRpc\JsonRpcConnection;
use gipfl\ZfDb\Adapter\Adapter as Db;
use Icinga\Application\Config;
use Icinga\Application\Icinga;
use Psr\Log\LoggerInterface;
use React\ChildProcess\Process;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use React\Promise\ExtendedPromiseInterface;
use React\Promise\Promise;

use function array_shift;
use function React\Promise\resolve;

class JobRunner implements DbBasedComponent
{
    protected LoopInterface $loop;
    protected ProcessList $running;
    protected LoggerInterface $logger;
    protected ?Db $db = null;
    protected ?LogProxy $logProxy = null;
    protected ?TimerInterface $timer = null;

    /** @var Promise[] */
    protected array $runningTasks = [];
    protected array $scheduledTasks = [];
    protected int $checkInterval = 10;

    public function __construct(LoopInterface $loop, LoggerInterface $logger)
    {
        $this->loop = $loop;
        $this->running = new ProcessList();
        $this->logger = $logger;
    }

    public function forwardLog(LogProxy $logProxy): void
    {
        $this->logProxy = $logProxy;
    }

    protected function getTaskList(): array
    {
        $config = Config::module('eventtracker');

        $tasks = [
            'expire',
            'hostlist',
        ];
        if (Icinga::app()->getModuleManager()->hasLoaded('monitoring')) {
            $tasks[] = 'ido';
            $tasks[] = 'idostate';
        }
        if ($config->get('scom', 'simulation_file') || $config->get('scom', 'db_resource')) {
            $tasks[] = 'scom';
        }

        return $tasks;
    }

    /**
     * @param Db $db
     * @return ExtendedPromiseInterface
     */
    public function initDb(Db $db)
    {
        $this->db = $db;
        $check = function () {
            try {
                $this->runNextPendingTask();
            } catch (\Exception $e) {
                $this->logger->error($e->getMessage());
            }
        };
        $schedule = function () {
            $taskNames = $this->getTaskList();
            foreach ($taskNames as $taskName) {
                if (! isset($this->scheduledTasks[$taskName])) {
                    $this->scheduledTasks[$taskName] = $taskName;
                }
            }
        };
        if ($this->timer === null) {
            $this->loop->futureTick($check);
        }
        if ($this->timer !== null) {
            $this->logger->info('Cancelling former timer');
            $this->loop->cancelTimer($this->timer);
        }
        $this->timer = $this->loop->addPeriodicTimer($this->checkInterval, $check);
        $this->timer = $this->loop->addPeriodicTimer(7, $schedule);

        return resolve(null);
    }

    public function stopDb(): ExtendedPromiseInterface
    {
        $this->scheduledTasks = [];
        if ($this->timer !== null) {
            $this->loop->cancelTimer($this->timer);
            $this->timer = null;
        }
        $terminateProcesses = ProcessKiller::terminateProcesses($this->running, $this->loop);
        foreach ($this->runningTasks as $id => $promise) {
            $promise->cancel();
        }
        $this->runningTasks = [];
        $this->db = null;

        return $terminateProcesses;
    }

    protected function runNextPendingTask()
    {
        if ($this->timer === null) {
            // Reset happened. Stopping?
            return;
        }

        if (! empty($this->runningTasks)) {
            return;
        }
        while (! empty($this->scheduledTasks)) {
            if ($this->runNextTask()) {
                break;
            }
        }
    }

    protected function runNextTask(): bool
    {
        $name = array_shift($this->scheduledTasks);
        try {
            $this->runSync($name);
        } catch (\Exception $e) {
            $this->logger->error("Trying to schedule '$name' failed: " . $e->getMessage());
        }

        return false;
    }

    protected function runSync($what)
    {
        $this->logger->debug("Sync ($what) starting");
        $arguments = [
            'eventtracker',
            'sync',
            $what,
            '--debug',
            '--rpc'
        ];
        $cli = new IcingaCliRpc();
        $cli->setArguments($arguments);
        $cli->on('start', function (Process $process) {
            $this->onProcessStarted($process);
        });

        // Happens on protocol (Netstring) errors or similar:
        $cli->on('error', function (\Exception $e) {
            $this->logger->error('UNEXPECTED: ' . rtrim($e->getMessage()));
        });
        if ($this->logProxy) {
            $cli->rpc()->then(function (JsonRpcConnection $rpc) use ($what) {
                $logger = clone($this->logProxy);
                $logger->setPrefix("Sync ($what): ");
                $handler = new NamespacedPacketHandler();
                $handler->registerNamespace('logger', $logger);
                $rpc->setHandler($handler);
            });
        }
        unset($this->scheduledTasks[$what]);
        $this->runningTasks[$what] = $cli->run($this->loop)->then(function () use ($what) {
            $this->logger->debug("Job ($what) finished");
        }, function (\Exception $e) use ($what) {
            $this->logger->error("Job ($what) failed: " . $e->getMessage());
        })->always(function () use ($what) {
            unset($this->runningTasks[$what]);
            $this->loop->futureTick(function () {
                $this->runNextPendingTask();
            });
        });
    }

    public function getProcessList(): ProcessList
    {
        return $this->running;
    }

    protected function onProcessStarted(Process $process)
    {
        $this->running->attach($process);
    }

    public function __destruct()
    {
        $this->stopDb();
        $this->logProxy = null;
    }
}
