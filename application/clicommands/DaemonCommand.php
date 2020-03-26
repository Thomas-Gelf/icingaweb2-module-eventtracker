<?php

namespace Icinga\Module\Eventtracker\Clicommands;

use Icinga\Module\Eventtracker\Daemon\BackgroundDaemon;

class DaemonCommand extends Command
{
    public function runAction()
    {
        $this->app->getModuleManager()->loadEnabledModules();
        $daemon = new BackgroundDaemon();
        $daemon->run();
    }
}
