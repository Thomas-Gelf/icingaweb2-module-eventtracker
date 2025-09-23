<?php

namespace Icinga\Module\Eventtracker\Daemon\RpcNamespace;

use Exception;
use gipfl\Process\ProcessKiller;
use gipfl\Protocol\JsonRpc\Handler\NamespacedPacketHandler;
use gipfl\Protocol\JsonRpc\JsonRpcConnection;
use Icinga\Module\Eventtracker\Daemon\IcingaCliRpc;
use Icinga\Module\Eventtracker\Daemon\LogProxy;
use Icinga\Module\Eventtracker\Db\DbCleanup;
use Icinga\Module\Eventtracker\Db\DbCleanupFilter;
use Icinga\Module\Eventtracker\DbFactory;
use Psr\Log\LoggerInterface;
use React\ChildProcess\Process;
use React\EventLoop\Loop;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use RuntimeException;

use function Clue\React\Block\await;

class RpcNamespaceCleanup
{
    protected LoggerInterface $logger;
    protected ?PromiseInterface $pendingCleanup = null;
    protected ?Process $runningCleanup = null;
    protected LogProxy $logProxy;
    protected bool $runQueries;

    public function __construct(LoggerInterface $logger, bool $runQueries = false)
    {
        $this->logger = $logger;
        $this->logProxy = new LogProxy($this->logger);
        $this->logProxy->setPrefix("DB Cleanup (process): ");
        $this->runQueries = $runQueries;
    }

    public function isCleaningUp(): bool
    {
        return $this->pendingCleanup !== null;
    }

    /**
     * @param \Icinga\Module\Eventtracker\Db\DbCleanupFilter $filter
     * @return bool
     */
    public function deleteIssuesRequest($filter): bool
    {
        $filter = DbCleanupFilter::fromSerialization($filter);
        if ($this->runQueries) {
            $this->runCleanup('issue', $filter, false);
        } else {
            $this->runViaCli(__FUNCTION__, $filter);
        }

        return true;
    }

    /**
     * @param \Icinga\Module\Eventtracker\Db\DbCleanupFilter $filter
     * @return PromiseInterface<int>
     */
    public function simulateDeleteIssuesRequest($filter): PromiseInterface
    {
        $filter = DbCleanupFilter::fromSerialization($filter);
        $this->logger->notice(json_encode($filter) . var_export($this->runQueries, 1));
        if ($this->runQueries) {
            await(\React\Promise\Timer\sleep(10));
            return $this->runCleanup('issue', $filter, true);
        } else {
            return $this->runViaCli(__FUNCTION__, $filter);
        }
    }

    /**
     * @param \Icinga\Module\Eventtracker\Db\DbCleanupFilter $filter
     * @return bool
     */
    public function deleteHistoryRequest($filter): bool
    {
        $filter = DbCleanupFilter::fromSerialization($filter);
        if ($this->runQueries) {
            $this->runCleanup('issue_history', $filter, false);
        } else {
            $this->runViaCli(__FUNCTION__, $filter);
        }

        return true;
    }

    /**
     * @param \Icinga\Module\Eventtracker\Db\DbCleanupFilter $filter
     * @return PromiseInterface<int>
     */
    public function simulateDeleteHistoryRequest($filter): PromiseInterface
    {
        $filter = DbCleanupFilter::fromSerialization($filter);
        if ($this->runQueries) {
            return $this->runCleanup('issue_history', $filter, true);
        } else {
            return $this->runViaCli(__FUNCTION__, $filter);
        }
    }

    protected function runViaCli(string $method, DbCleanupFilter $filter)
    {
        if ($this->isCleaningUp()) {
            throw new RuntimeException('Another DB cleanup is already running');
        }
        $arguments = ['eventtracker', 'delete', 'rpc', '--debug'];
        $cli = new IcingaCliRpc();
        $cli->setArguments($arguments);
        $cli->on('start', function (Process $process) {
            $this->runningCleanup = $process;
        });
        $method = preg_replace('/Request$/', '', $method);

        // Happens on protocol (Netstring) errors or similar:
        $cli->on('error', function (Exception $e) {
            $this->logger->error('UNEXPECTED: ' . rtrim($e->getMessage()));
            if ($this->runningCleanup) {
                ProcessKiller::terminateProcess($this->runningCleanup, Loop::get());
                $this->runningCleanup = null;
            }
            $this->pendingCleanup = null;
        });
        $cli->run()->then(function () use ($method) {
            // $this->logger->notice('Process exited');
        }, function (Exception $e) use ($arguments) {
            $this->logger->error(sprintf(
                'Cleanup sub-process (%s) failed: %s',
                implode(', ', $arguments),
                $e->getMessage()
            ));
        });
        /** @var JsonRpcConnection $rpc */
        return $this->pendingCleanup = $cli->rpc()->then(function (JsonRpcConnection $rpc) {
            // we proxy sub-process logs
            $handler = new NamespacedPacketHandler();
            $handler->registerNamespace('logger', $this->logProxy);
            $rpc->setHandler($handler);
            return $rpc;
        })->then(function (JsonRpcConnection $rpc) use ($method, $filter) {
            $result = $rpc->request("cleanup.$method", [$filter]);

            // then is necessary, otherwise we would close the process before getting the resujlt
            return $result->then(function ($result) {
                $this->pendingCleanup = null;
                $this->runningCleanup->close();
                $this->runningCleanup = null;
                return $result;
            }, function (Exception $e) {
                $this->pendingCleanup = null;
                $this->runningCleanup = null;
                throw $e;
            });
        });
    }

    /**
     * @return PromiseInterface<int>
     */
    protected function runCleanup(string $table, DbCleanupFilter $filter, bool $simulate): PromiseInterface
    {
        $deferred = new Deferred();
        Loop::futureTick(function () use ($table, $filter, $simulate, $deferred) {
            $cleanup = new DbCleanup(DbFactory::db(), $table, $filter, $this->logger);
            if ($simulate) {
                $deferred->resolve($cleanup->count());
            } else {
                $deferred->resolve($cleanup->delete());
            }
        });

        return $deferred->promise();
    }
}
