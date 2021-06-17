<?php

namespace Icinga\Module\Eventtracker\Clicommands;

use Icinga\Module\Eventtracker\Syslog\SyslogDaemon;
use React\EventLoop\Factory;

class SyslogCommand extends Command
{
    public function listenAction()
    {
        $loop = Factory::create();
        $daemon = new SyslogDaemon('/var/lib/icingaweb2/eventracker-syslog.sock');
        $loop->futureTick(function () use ($daemon, $loop) {
            $daemon->run($loop);
        });
        $loop->run();
    }
}
