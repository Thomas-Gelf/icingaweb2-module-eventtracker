<?php

namespace Icinga\Module\Eventtracker\Controllers;

use Icinga\Module\Eventtracker\Configuration;
use Icinga\Module\Eventtracker\Daemon\RemoteClient;

use function React\Async\await;
use function React\Promise\Timer\timeout;

trait AsyncControllerHelper
{
    protected ?RemoteClient $remoteClient = null;

    protected function syncRpcCall($method, $params = [], $timeout = 30)
    {
        return await(timeout($this->remoteClient()->request($method, $params), $timeout));
    }

    protected function remoteClient(): RemoteClient
    {
        if ($this->remoteClient === null) {
            $this->remoteClient = new RemoteClient(Configuration::getSocketPath());
        }

        return $this->remoteClient;
    }
}
