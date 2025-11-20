<?php

namespace Icinga\Module\Eventtracker\Daemon;

use gipfl\ZfDb\Adapter\Pdo\PdoAdapter;
use gipfl\ZfDbStore\ZfDbStore;
use Psr\Log\LoggerInterface;
use React\EventLoop\Loop;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;

trait SimpleDbBasedComponent
{
    protected ?PdoAdapter $db = null;
    protected ?ZfDbStore $dbStore = null;

    public function initDb(PdoAdapter $db): void
    {
        $this->db = $db;
        $this->dbStore = new ZfDbStore($db);
        Loop::futureTick(function () {
            if (method_exists($this, 'onDbReady')) {
                try {
                    $this->onDbReady();
                } catch (\Exception $e) {
                    if (isset($this->logger) && $this->logger instanceof LoggerInterface) {
                        $this->logger->critical(__CLASS__ . ' failed on onDbReady(): ' . $e->getMessage());
                    }
                }
            }
        });
    }

    final public function stopDb(): PromiseInterface
    {
        $this->db = null;
        $this->dbStore = null;
        $deferred = new Deferred();
        Loop::futureTick(function () use ($deferred) {
            if (method_exists($this, 'onDbLost')) {
                try {
                    $this->onDbLost();
                    $deferred->resolve(null);
                } catch (\Exception $e) {
                    if (isset($this->logger) && $this->logger instanceof LoggerInterface) {
                        $this->logger->critical(__CLASS__ . ' failed on onDbLost(): ' . $e->getMessage());
                    }
                    $deferred->reject($e);
                }
            } else {
                $deferred->resolve(null);
            }
        });

        return $deferred->promise();
    }
}
