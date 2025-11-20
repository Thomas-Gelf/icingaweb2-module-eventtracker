<?php

namespace Icinga\Module\Eventtracker\Daemon;

use gipfl\ZfDb\Adapter\Pdo\PdoAdapter;
use Icinga\Module\Eventtracker\Db\ConfigStore;
use Icinga\Module\Eventtracker\Engine\Downtime\DowntimeRunner;
use Icinga\Module\Eventtracker\Engine\InputRunner;
use Psr\Log\LoggerInterface;
use React\Promise\PromiseInterface;

use function React\Promise\resolve;

class InputAndChannelRunner implements DbBasedComponent
{
    protected DowntimeRunner $downtimeRunner;
    protected LoggerInterface $logger;
    protected ?InputRunner $runner = null;

    public function __construct(DowntimeRunner $downtimeRunner, LoggerInterface $logger)
    {
        $this->downtimeRunner = $downtimeRunner;
        $this->logger = $logger;
    }

    public function initDb(PdoAdapter $db): void
    {
        $store = new ConfigStore($db, $this->logger);
        $this->runner = new InputRunner($store, $this->downtimeRunner, $this->logger);
        $this->runner->setLogger($this->logger);
        $this->runner->start();
    }

    public function getInputRunner(): ?InputRunner
    {
        return $this->runner;
    }

    public function stopDb(): PromiseInterface
    {
        if ($this->runner) {
            $this->runner->stop();
            $this->runner = null;
        }

        return resolve(null);
    }

    public function __destruct()
    {
        $this->stopDb();
    }
}
