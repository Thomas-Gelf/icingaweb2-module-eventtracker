<?php

namespace Icinga\Module\Eventtracker\Clicommands;

use Icinga\Module\Eventtracker\Daemon\BackgroundDaemon;
use Icinga\Module\Eventtracker\Daemon\RpcNamespace\RpcNamespaceProcess;

class DaemonCommand extends Command
{
    use CommandWithLoop;

    public function runAction()
    {
        $daemon = new BackgroundDaemon($this->logger);
        $daemon->on(RpcNamespaceProcess::ON_RESTART, function () use ($daemon) {
            $daemon->reload();
        });
        $daemon->run($this->loop());
        $this->loop()->run();
    }
}
