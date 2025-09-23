<?php

namespace Icinga\Module\Eventtracker\Clicommands;

use gipfl\Log\Filter\LogLevelFilter;
use gipfl\Log\IcingaWeb\IcingaLogger;
use gipfl\Log\Logger;
use gipfl\Log\Writer\JournaldLogger;
use gipfl\Log\Writer\JsonRpcConnectionWriter;
use gipfl\Log\Writer\SystemdStdoutWriter;
use gipfl\Log\Writer\WritableStreamWriter;
use gipfl\Protocol\JsonRpc\Handler\NamespacedPacketHandler;
use gipfl\Protocol\JsonRpc\JsonRpcConnection;
use gipfl\Protocol\NetString\StreamWrapper;
use gipfl\SystemD\systemd;
use Icinga\Module\Eventtracker\Daemon\Application;
use React\EventLoop\Loop;
use React\Promise\PromiseInterface;
use React\Stream\ReadableResourceStream;
use React\Stream\WritableResourceStream;

trait CommandWithLoop
{
    protected ?Logger $logger = null;
    protected ?JsonRpcConnection $rpc = null;
    protected ?NamespacedPacketHandler $rpcHandler = null;

    public function init()
    {
        $this->app->getModuleManager()->loadEnabledModules();
        $this->clearProxySettings();
        $this->initializeLogger();
        if ($this->isRpc()) {
            $this->enableRpc();
        }
    }

    protected function enableRpc()
    {
        $handler = $this->rpcHandler = new NamespacedPacketHandler();
        // in case we provide Methods:
        // $handler->registerNamespace('eventtracker', new DbRunner($this->logger, $this->loop()));
        $this->rpc = $this->prepareJsonRpc($handler);
        $this->logger->addWriter(new JsonRpcConnectionWriter($this->rpc));
    }

    /**
     * Prepares a JSON-RPC Connection on STDIN/STDOUT
     */
    protected function prepareJsonRpc($handler): JsonRpcConnection
    {
        return new JsonRpcConnection(new StreamWrapper(
            new ReadableResourceStream(STDIN),
            new WritableResourceStream(STDOUT)
        ), $handler);
    }

    protected function initializeLogger()
    {
        $this->logger = $logger = new Logger();
        $this->eventuallyFilterLog($this->logger);
        IcingaLogger::replace($logger);
        if ($this->isRpc()) {
            // Writer will be added later
            return;
        }
        if (systemd::startedThisProcess()) {
            if (@file_exists(JournaldLogger::JOURNALD_SOCKET)) {
                $logger->addWriter((new JournaldLogger())->setIdentifier(Application::LOG_NAME));
            } else {
                $logger->addWriter(new SystemdStdoutWriter(Loop::get()));
            }
        } else {
            $logger->addWriter(new WritableStreamWriter(new WritableResourceStream(STDERR)));
        }
    }

    protected function eventuallyFilterLog(Logger $logger)
    {
        /** @noinspection PhpStatementHasEmptyBodyInspection */
        if ($this->isDebugging) {
            // Hint: no need to filter
            // $this->logger->addFilter(new LogLevelFilter('debug'));
        } elseif ($this->isVerbose) {
            $logger->addFilter(new LogLevelFilter('info'));
        } else {
            $logger->addFilter(new LogLevelFilter('notice'));
        }
    }

    protected function isRpc(): bool
    {
        return (bool) $this->params->get('rpc');
    }

    protected function clearProxySettings()
    {
        $settings = [
            'http_proxy',
            'https_proxy',
            'HTTPS_PROXY',
            'ALL_PROXY',
        ];
        foreach ($settings as $setting) {
            putenv("$setting=");
        }
    }

    protected function runWithLoop($callable)
    {
        Loop::futureTick(function () use ($callable) {
            try {
                $result = $callable();

                if ($result instanceof PromiseInterface) {
                    $result->then(function () {
                        Loop::stop();
                    }, function ($error) {
                        if ($error instanceof \Throwable) {
                            $this->failNice($error->getMessage());
                        } else {
                            $this->failNice($error);
                        }
                    });
                } else {
                    Loop::addTimer(0.3, function () {
                        Loop::stop();
                    });
                }
            } catch (\Throwable $e) {
                $this->failNice($e->getMessage());
            }
        });
        Loop::run();
    }

    public function failNice($msg)
    {
        if ($this->isRpc()) {
            $this->logger->error($msg);
        } else {
            \printf("%s: %s\n", $this->screen->colorize('ERROR', 'red'), $msg);
        }

        Loop::addTimer(0.3, function () {
            Loop::stop();
            exit(1);
        });
    }

    public function fail($msg)
    {
        /** @var Command $this */
        echo $this->screen->colorize("$msg\n", 'red');
        exit(1);
    }
}
