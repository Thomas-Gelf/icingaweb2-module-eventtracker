<?php

namespace Icinga\Module\Eventtracker;

use Icinga\Module\Eventtracker\Daemon\RemoteClient;
use React\EventLoop\Loop;

use function Clue\React\Block\await as block_await;

/**
 * Hint: this code is NOT to be used in the main (async) daemon
 */
class EventReceiver
{
    protected ?RemoteClient $remoteClient = null;

    /**
     * Hint: this is a compatibility layer for other modules. DB is no longer required,
     *       and actions will always run in the daemon
     */
    public function __construct($db = null, $runActions = null)
    {
    }

    public function processEvent(Event $event): ?Issue
    {
        $issue = block_await($this->remoteClient()->request('event.receive', [$event]), Loop::get());
        Loop::stop();
        if ($issue) {
            return Issue::fromSerialization($issue);
        }

        return null;
    }

    protected function remoteClient(): RemoteClient
    {
        if ($this->remoteClient === null) {
            $this->remoteClient =  new RemoteClient(Configuration::getSocketPath());
        }

        return $this->remoteClient;
    }
}
