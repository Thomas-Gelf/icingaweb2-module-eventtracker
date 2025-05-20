<?php

namespace Icinga\Module\Eventtracker\Daemon;

use gipfl\ZfDb\Adapter\Adapter as Db;
use gipfl\ZfDbStore\ZfDbStore;
use React\EventLoop\Loop;
use React\Promise\Deferred;
use React\Promise\Promise;
use React\Promise\PromiseInterface;

trait SimpleDbBasedComponent
{
    protected ?Db $db = null;
    protected ?ZfDbStore $dbStore = null;

    final public function initDb(Db $db): Promise
    {
        $this->db = $db;
        $this->dbStore = new ZfDbStore($db);
        $deferred = new Deferred();
        Loop::futureTick(function () use ($deferred) {
            if (method_exists($this, 'onDbReady')) {
                try {
                    $this->onDbReady();
                    $deferred->resolve(null);
                } catch (\Exception $e) {
                    if (isset($this->logger) && $this->logger instanceof Loop) {
                        $this->logger->critical(__CLASS__ . ' failed on onDbReady(): ' . $e->getMessage());
                    }
                    $deferred->reject($e);
                }
            } else {
                $deferred->resolve(null);
            }
        });

        return $deferred->promise();
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
                    if (isset($this->logger) && $this->logger instanceof Loop) {
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
