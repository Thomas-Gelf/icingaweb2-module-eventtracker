<?php

namespace Icinga\Module\Eventtracker\Clicommands;

use Icinga\Module\Eventtracker\Daemon\BackgroundDaemon;

class DaemonCommand extends Command
{
    use CommandWithLoop;

    public function runAction()
    {
        $daemon = new BackgroundDaemon($this->logger);
        $daemon->run($this->loop());
        $this->loop()->run();
    }
}
