<?php

namespace Icinga\Module\Eventtracker;

use Icinga\Module\Eventtracker\Daemon\RemoteClient;
use React\EventLoop\Factory;
use function Clue\React\Block\await as block_await;

class EventReceiver
{
    /** @var ?RemoteClient */
    protected $remoteClient;

    /** @var \React\EventLoop\LoopInterface */
    protected $loop;

    /**
     * Hint: this is a compatibility layer for other modules. DB is no longer required,
     *       and actions will always run in the daemon
     */
    public function __construct($db = null, $runActions = null)
    {
    }

    public function processEvent(Event $event): ?Issue
    {
        $issue = block_await($this->remoteClient()->request('event.receive', [$event]), $this->loop());
        $this->loop()->stop();
        if ($issue) {
            return Issue::fromSerialization($issue);
        }

        return null;
    }

    protected function loop()
    {
        if ($this->loop === null) {
            $this->loop = Factory::create();
        }

        return $this->loop;
    }

    protected function remoteClient(): RemoteClient
    {
        if ($this->remoteClient === null) {
            $this->remoteClient =  new RemoteClient(Configuration::getSocketPath(), $this->loop());
        }

        return $this->remoteClient;
    }
}
