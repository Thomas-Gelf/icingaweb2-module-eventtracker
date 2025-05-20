<?php

namespace Icinga\Module\Eventtracker\Clicommands;

use Icinga\Module\Eventtracker\Daemon\BackgroundDaemon;
use Icinga\Module\Eventtracker\Daemon\RpcNamespace\RpcNamespaceProcess;
use React\EventLoop\Loop;

class DaemonCommand extends Command
{
    use CommandWithLoop;

    public function runAction()
    {
        $daemon = new BackgroundDaemon($this->logger);
        $daemon->on(RpcNamespaceProcess::ON_RESTART, fn ()  => $daemon->reload());
        $daemon->run();
        Loop::run();
    }
}
