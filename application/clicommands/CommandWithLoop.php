<?php

namespace Icinga\Module\Eventtracker\Clicommands;

use gipfl\Cli\Tty;
use gipfl\Log\Filter\LogLevelFilter;
use gipfl\Log\IcingaWeb\IcingaLogger;
use gipfl\Log\Logger;
use gipfl\Log\Writer\JsonRpcWriter;
use gipfl\Log\Writer\SystemdStdoutWriter;
use gipfl\Log\Writer\WritableStreamWriter;
use gipfl\Protocol\JsonRpc\Connection;
use gipfl\Protocol\NetString\StreamWrapper;
use gipfl\SystemD\systemd;
use React\EventLoop\Factory as Loop;
use React\EventLoop\LoopInterface;
use React\Stream\ReadableResourceStream;
use React\Stream\WritableResourceStream;

trait CommandWithLoop
{
    /** @var LoopInterface */
    private $loop;

    private $loopStarted = false;

    protected $logger;

    /** @var Connection|null */
    protected $rpc;

    public function init()
    {
        /** @var Command $this */
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

        return $this;
    }

    protected function stopMainLoop()
    {
        if ($this->loopStarted) {
            $this->loopStarted = false;
            $this->loop()->stop();
        }

        return $this;
    }

    protected function enableRpc()
    {
        if (Tty::isSupported()) {
            $stdin = (new Tty($this->loop()))->setEcho(false)->stdin();
        } else {
            $stdin = new ReadableResourceStream(STDIN, $this->loop());
        }
        $netString = new StreamWrapper(
            $stdin,
            new WritableResourceStream(STDOUT, $this->loop())
        );
        $this->rpc = new Connection();
        $this->rpc->handle($netString);
        $this->logger->addWriter(new JsonRpcWriter($this->rpc));
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
            $logger->addWriter(new SystemdStdoutWriter($loop));
        } else {
            $logger->addWriter(new WritableStreamWriter(new WritableResourceStream(STDERR, $loop)));
        }
    }

    protected function eventuallyFilterLog(Logger $logger)
    {
        /** @var Command $this */
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

    protected function isRpc()
    {
        /** @var Command $this */
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

    public function fail($msg)
    {
        /** @var Command $this */
        echo $this->screen->colorize("$msg\n", 'red');
        exit(1);
    }
}
