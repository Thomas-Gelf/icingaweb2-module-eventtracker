<?php

namespace Icinga\Module\Eventtracker\Controllers;

use Clue\React\Block;
use Icinga\Module\Eventtracker\Configuration;
use Icinga\Module\Eventtracker\Daemon\RemoteClient;
use React\EventLoop\Factory as Loop;

trait AsyncControllerHelper
{
    protected $loop;

    /** @var RemoteClient */
    protected $remoteClient;

    protected function syncRpcCall($method, $params = [], $timeout = 30)
    {
        return Block\await($this->remoteClient()->request($method, $params), $this->loop(), $timeout);
    }

    protected function remoteClient(): RemoteClient
    {
        if ($this->remoteClient === null) {
            $this->remoteClient = new RemoteClient(Configuration::getSocketPath(), $this->loop());
        }

        return $this->remoteClient;
    }

    protected function loop()
    {
        // Hint: we're not running this loop right now
        if ($this->loop === null) {
            $this->loop = Loop::create();
        }

        return $this->loop;
    }
}
