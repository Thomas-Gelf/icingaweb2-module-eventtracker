<?php

namespace Icinga\Module\Eventtracker\Daemon;

use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;
use React\Socket\UnixServer;
use React\Stream\Util;

use function file_exists;
use function umask;
use function unlink;

class ControlSocket implements EventEmitterInterface
{
    use EventEmitterTrait;

    protected string $path;
    protected ?UnixServer $server = null;

    public function __construct(string $path)
    {
        $this->path = $path;
        $this->eventuallyRemoveSocketFile();
    }

    public function run()
    {
        $this->listen();
    }

    protected function listen()
    {
        $old = umask(0000);
        $server = new UnixServer('unix://' . $this->path);
        umask($old);
        Util::forwardEvents($server, $this, ['connection' ,'error']);
        $this->server = $server;
    }

    public function shutdown()
    {
        if ($this->server) {
            $this->server->close();
            $this->server = null;
        }

        $this->eventuallyRemoveSocketFile();
    }

    protected function eventuallyRemoveSocketFile()
    {
        if (file_exists($this->path)) {
            unlink($this->path);
        }
    }
}
