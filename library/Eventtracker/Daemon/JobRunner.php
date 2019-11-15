<?php

namespace Icinga\Module\Eventtracker\Daemon;

use gipfl\IcingaCliDaemon\FinishedProcessState;
use gipfl\IcingaCliDaemon\IcingaCliRpc;
use Icinga\Application\Logger;
use Icinga\Data\Db\DbConnection as Db;
use React\ChildProcess\Process;
use React\EventLoop\LoopInterface;
use React\Promise\FulfilledPromise;
use React\Promise\Promise;

class JobRunner implements DbBasedComponent
{
    /** @var Db */
    protected $db;

    /** @var LoopInterface */
    protected $loop;

    /** @var array */
    protected $scheduledTasks = [];

    /** @var Promise[] */
    protected $runningTasks = [];

    protected $checkInterval = 10;

    /** @var \React\EventLoop\TimerInterface */
    protected $timer;

    /** @var LogProxy */
    protected $logProxy;

    /** @var ProcessList */
    protected $running;

    public function __construct(LoopInterface $loop)
    {
        $this->loop = $loop;
        $this->running = new ProcessList($loop);
    }

    public function forwardLog(LogProxy $logProxy)
    {
        $this->logProxy = $logProxy;

        return $this;
    }

    /**
     * @param Db $db
     * @return \React\Promise\ExtendedPromiseInterface
     */
    public function initDb(Db $db)
    {
        $this->db = $db;
        $check = function () {
            try {
                $this->runNextPendingTask();
            } catch (\Exception $e) {
                Logger::error($e->getMessage());
            }
        };
        $schedule = function () {
            $taskNames = [
                'scom',
                'ido',
                'expire',
            ];
            foreach ($taskNames as $taskName) {
                if (! isset($this->scheduledTasks[$taskName])
                    && ! isset($this->scheduledTasks[$taskName])
                ) {
                    $this->scheduledTasks[$taskName] = $taskName;
                }
            }
        };
        if ($this->timer === null) {
            $this->loop->futureTick($check);
        }
        if ($this->timer !== null) {
            Logger::info('Cancelling former timer');
            $this->loop->cancelTimer($this->timer);
        }
        $this->timer = $this->loop->addPeriodicTimer($this->checkInterval, $check);
        $this->timer = $this->loop->addPeriodicTimer(3, $schedule);

        return new FulfilledPromise();
    }

    /**
     * @return \React\Promise\ExtendedPromiseInterface
     */
    public function stopDb()
    {
        $this->scheduledTasks = [];
        if ($this->timer !== null) {
            $this->loop->cancelTimer($this->timer);
            $this->timer = null;
        }
        $allFinished = $this->running->killOrTerminate();
        foreach ($this->runningTasks as $id => $promise) {
            $promise->cancel();
        }
        $this->runningTasks = [];

        return $allFinished;
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

    /**
     * @return bool
     */
    protected function runNextTask()
    {
        $name = \array_shift($this->scheduledTasks);
        try {
            $this->runSync($name);
        } catch (\Exception $e) {
            Logger::error("Trying to schedule '$name' failed: " . $e->getMessage());
        }

        return false;
    }

    protected function runSync($what)
    {
        Logger::debug("Sync ($what) starting");
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
            Logger::error('UNEXPECTED: ' . rtrim($e->getMessage()));
        });
        if ($this->logProxy) {
            $logger = clone($this->logProxy);
            $logger->setPrefix("Sync ($what): ");
            $cli->rpc()->setHandler($logger, 'logger');
        }
        unset($this->scheduledTasks[$what]);
        $this->runningTasks[$what] = $cli->run($this->loop)->then(function () use ($what) {
            Logger::debug("Job ($what) finished");
        })->otherwise(function (\Exception $e) use ($what) {
            Logger::error("Job ($what) failed: " . $e->getMessage());
        })->otherwise(function (FinishedProcessState $state) use ($what) {
            Logger::error("Job ($what) failed: " . $state->getReason());
        })->always(function () use ($what) {
            unset($this->runningTasks[$what]);
            $this->loop->futureTick(function () {
                $this->runNextPendingTask();
            });
        });
    }

    /**
     * @return ProcessList
     */
    public function getProcessList()
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
        $this->loop = null;
    }
}
