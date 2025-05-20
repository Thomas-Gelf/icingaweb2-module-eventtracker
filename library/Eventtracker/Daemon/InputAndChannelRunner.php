<?php

namespace Icinga\Module\Eventtracker\Daemon;

use gipfl\ZfDb\Adapter\Adapter as Db;
use Icinga\Module\Eventtracker\Db\ConfigStore;
use Icinga\Module\Eventtracker\Engine\Downtime\DowntimeRunner;
use Icinga\Module\Eventtracker\Engine\InputRunner;
use Psr\Log\LoggerInterface;

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

    /**
     * @return \React\Promise\ExtendedPromiseInterface
     */
    public function initDb(Db $db)
    {
        $store = new ConfigStore($db, $this->logger);
        $this->runner = new InputRunner($store, $this->downtimeRunner, $this->logger);
        $this->runner->setLogger($this->logger);
        $this->runner->start();

        return resolve(null);
    }

    public function getInputRunner(): ?InputRunner
    {
        return $this->runner;
    }

    /**
     * @return \React\Promise\ExtendedPromiseInterface
     */
    public function stopDb()
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
