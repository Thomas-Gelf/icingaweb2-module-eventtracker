<?php

namespace Icinga\Module\Eventtracker\Daemon;

use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;
use Exception;
use gipfl\Log\Logger;
use gipfl\Protocol\JsonRpc\Error;
use gipfl\Protocol\JsonRpc\Handler\FailingPacketHandler;
use gipfl\Protocol\JsonRpc\Handler\NamespacedPacketHandler;
use gipfl\Protocol\JsonRpc\JsonRpcConnection;
use gipfl\Protocol\NetString\StreamWrapper;
use gipfl\Socket\UnixSocketInspection;
use gipfl\Socket\UnixSocketPeer;
use Icinga\Module\Eventtracker\Daemon\RpcNamespace\RpcNamespaceCleanup;
use Icinga\Module\Eventtracker\Daemon\RpcNamespace\RpcNamespaceEvent;
use Icinga\Module\Eventtracker\Daemon\RpcNamespace\RpcNamespaceEventtracker;
use Icinga\Module\Eventtracker\Daemon\RpcNamespace\RpcNamespaceIssue;
use Icinga\Module\Eventtracker\Daemon\RpcNamespace\RpcNamespaceProcess;
use Icinga\Module\Eventtracker\Daemon\RpcNamespace\RpcNamespaceLogger;
use Icinga\Module\Eventtracker\Engine\Downtime\DowntimeRunner;
use Psr\Log\LoggerInterface;
use React\EventLoop\Loop;
use React\Promise\PromiseInterface;
use React\Socket\ConnectionInterface;
use React\Stream\Util;

use function posix_getegid;
use function React\Promise\resolve;

class RemoteApi implements EventEmitterInterface
{
    use EventEmitterTrait;

    protected LoggerInterface $logger;
    protected InputAndChannelRunner $runner;
    protected ?ControlSocket $controlSocket = null;

    protected DowntimeRunner $downtimeRunner;
    protected DaemonDb $daemonDb;
    protected NamespacedPacketHandler $rpcHandler;

    public function __construct(
        InputAndChannelRunner $runner,
        DowntimeRunner        $downtimeRunner,
        DaemonDb              $daemonDb,
        LoggerInterface       $logger
    ) {
        $this->runner = $runner;
        $this->downtimeRunner = $downtimeRunner;
        $this->daemonDb = $daemonDb;
        $this->logger = $logger;
        $this->rpcHandler = $this->prepareRpcHandler();
    }

    public function run($socketPath)
    {
        $this->initializeControlSocket($socketPath);
    }

    protected function initializeControlSocket($path)
    {
        if (empty($path)) {
            throw new \InvalidArgumentException('Control socket path expected, got none');
        }
        $this->logger->info("[socket] launching control socket in $path");
        $socket = new ControlSocket($path);
        $socket->run();
        $this->addSocketEventHandlers($socket);
        $this->controlSocket = $socket;
    }

    protected function isAllowed(UnixSocketPeer $peer)
    {
        if ($peer->getUid() === 0) {
            return true;
        }
        $myGid = posix_getegid();
        $peerGid = $peer->getGid();
        // Hint: $myGid makes also part of id -G, this is the fast lane for those using
        //       php-fpm and the user icingaweb2 (with the very same main group as we have)
        if ($peerGid === $myGid) {
            return true;
        }

        $uid = $peer->getUid();
        return in_array($myGid, array_map('intval', explode(' ', `id -G $uid`)));
    }

    protected function addSocketEventHandlers(ControlSocket $socket)
    {
        $socket->on('connection', function (ConnectionInterface $connection) {
            $jsonRpc = new JsonRpcConnection(new StreamWrapper($connection));
            $jsonRpc->setLogger($this->logger);

            try {
                $peer = UnixSocketInspection::getPeer($connection);
            } catch (Exception $e) {
                $jsonRpc->setHandler(new FailingPacketHandler(Error::forException($e)));
                Loop::addTimer(3, function () use ($connection) {
                    $connection->close();
                });
                return;
            }

            if ($this->isAllowed($peer)) {
                $jsonRpc->setHandler($this->rpcHandler);
            } else {
                $jsonRpc->setHandler(new FailingPacketHandler(new Error(Error::METHOD_NOT_FOUND, sprintf(
                    '%s is not allowed to control this socket',
                    $peer->getUsername()
                ))));
                Loop::addTimer(10, function () use ($connection) {
                    $connection->close();
                });
            }
        });
        $socket->on('error', function (Exception $error) {
            // Connection error, Socket remains functional
            $this->logger->error($error->getMessage());
        });
    }

    public function shutdown(): PromiseInterface
    {
        if ($this->controlSocket) {
            $this->controlSocket->shutdown();
            unset($this->controlSocket);
        }

        return resolve(null);
    }

    private function prepareRpcHandler(): NamespacedPacketHandler
    {
        $rpcProcess = new RpcNamespaceProcess();
        Util::forwardEvents($rpcProcess, $this, [RpcNamespaceProcess::ON_RESTART]);
        $handler = new NamespacedPacketHandler();
        $handler->registerNamespace('eventtracker', new RpcNamespaceEventtracker(
            $this->downtimeRunner,
            $this->logger
        ));
        $nsCleanup = new RpcNamespaceCleanup($this->logger);
        $handler->registerNamespace('cleanup', $nsCleanup);
        $nsEvent = new RpcNamespaceEvent($this->runner, $this->downtimeRunner, $this->logger);
        $handler->registerNamespace('event', $nsEvent);
        $this->daemonDb->register($nsEvent);
        $nsIssue = new RpcNamespaceIssue($this->logger);
        $handler->registerNamespace('issue', $nsIssue);
        $this->daemonDb->register($nsIssue);
        $handler->registerNamespace('process', $rpcProcess);
        if ($this->logger instanceof Logger) {
            $handler->registerNamespace('logger', new RpcNamespaceLogger($this->logger));
        }

        return $handler;
    }
}
