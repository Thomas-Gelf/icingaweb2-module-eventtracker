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
use React\EventLoop\Factory as Loop;
use React\EventLoop\LoopInterface;
use React\Promise\ExtendedPromiseInterface;
use React\Stream\ReadableResourceStream;
use React\Stream\WritableResourceStream;

trait CommandWithLoop
{
    /** @var LoopInterface */
    private $loop;

    private $loopStarted = false;

    protected $logger;

    /** @var JsonRpcConnection|null */
    protected $rpc;

    public function init()
    {
        $this->app->getModuleManager()->loadEnabledModules();
        $this->clearProxySettings();
        $this->initializeLogger();
        if ($this->isRpc()) {
            $this->enableRpc();
        }
    }

    protected function loop()
    {
        if ($this->loop === null) {
            $this->loop = Loop::create();
        }

        return $this->loop;
    }

    protected function eventuallyStartMainLoop()
    {
        if (! $this->loopStarted) {
            $this->loopStarted = true;
            $this->loop()->run();
        }
    }

    protected function stopMainLoop()
    {
        if ($this->loopStarted) {
            $this->loopStarted = false;
            $this->loop()->stop();
        }
    }

    protected function enableRpc()
    {
        $handler = new NamespacedPacketHandler();
        // in case we provide Methods:
        // $handler->registerNamespace('eventtracker', new DbRunner($this->logger, $this->loop()));
        $this->rpc = $this->prepareJsonRpc($this->loop(), $handler);
        $this->logger->addWriter(new JsonRpcConnectionWriter($this->rpc));
    }

    /**
     * Prepares a JSON-RPC Connection on STDIN/STDOUT
     */
    protected function prepareJsonRpc(LoopInterface $loop, $handler): JsonRpcConnection
    {
        return new JsonRpcConnection(new StreamWrapper(
            new ReadableResourceStream(STDIN, $loop),
            new WritableResourceStream(STDOUT, $loop)
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
        $loop = $this->loop();
        if (systemd::startedThisProcess()) {
            if (@file_exists(JournaldLogger::JOURNALD_SOCKET)) {
                $logger->addWriter((new JournaldLogger())->setIdentifier(Application::LOG_NAME));
            } else {
                $logger->addWriter(new SystemdStdoutWriter($loop));
            }
        } else {
            $logger->addWriter(new WritableStreamWriter(new WritableResourceStream(STDERR, $loop)));
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

    public function failNice($msg)
    {
        if ($this->isRpc()) {
            $this->logger->error($msg);
        } else {
            \printf("%s: %s\n", $this->screen->colorize('ERROR', 'red'), $msg);
        }

        $this->loop->futureTick(function () {
            $this->loop->stop();
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
