<?php

namespace Icinga\Module\Eventtracker\Daemon;

use Exception;
use gipfl\Protocol\JsonRpc\JsonRpcConnection;
use gipfl\Protocol\NetString\StreamWrapper;
use React\ChildProcess\Process;
use React\EventLoop\Loop;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;

class IcingaCliRpc extends IcingaCli
{
    protected ?JsonRpcConnection $rpc = null;
    protected ?Deferred $waitingForRpc = null;

    protected function init()
    {
        $this->on('start', function (Process $process) {
            $netString = new StreamWrapper(
                $process->stdout,
                $process->stdin
            );
            $netString->on('error', function (Exception $e) {
                if ($this->waitingForRpc) {
                    $this->waitingForRpc->reject($e);
                }
                $this->emit('error', [$e]);
            });
            $this->rpc = new JsonRpcConnection($netString);
            if ($deferred = $this->waitingForRpc) {
                $this->waitingForRpc = null;
                $deferred->resolve($this->rpc);
            }
        });
    }

    /**
     * @return PromiseInterface <Connection>
     */
    public function rpc()
    {
        if (! $this->waitingForRpc) {
            $this->waitingForRpc = new Deferred();
        }

        if ($this->rpc) {
            Loop::futureTick(function () {
                if ($this->rpc && $deferred = $this->waitingForRpc) {
                    $this->waitingForRpc = null;
                    $deferred->resolve($this->rpc);
                }
            });
        }

        return $this->waitingForRpc->promise();
    }
}
