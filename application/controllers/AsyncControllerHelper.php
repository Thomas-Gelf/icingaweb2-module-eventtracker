<?php

namespace Icinga\Module\Eventtracker\Controllers;

use Clue\React\Block;
use Icinga\Module\Eventtracker\Configuration;
use Icinga\Module\Eventtracker\Daemon\RemoteClient;
use React\EventLoop\Loop;

trait AsyncControllerHelper
{
    protected ?RemoteClient $remoteClient = null;

    protected function syncRpcCall($method, $params = [], $timeout = 30)
    {
        return Block\await($this->remoteClient()->request($method, $params), Loop::get(), $timeout);
    }

    protected function remoteClient(): RemoteClient
    {
        if ($this->remoteClient === null) {
            $this->remoteClient = new RemoteClient(Configuration::getSocketPath());
        }

        return $this->remoteClient;
    }
}
