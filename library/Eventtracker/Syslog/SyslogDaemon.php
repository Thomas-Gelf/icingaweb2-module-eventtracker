<?php

namespace Icinga\Module\Eventtracker\Syslog;

use Icinga\Module\Eventtracker\DbFactory;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectionInterface;
use React\Socket\UnixServer;

class SyslogDaemon
{
    /** @var string */
    protected $socket;

    /** @var UnixServer */
    protected $server;

    /** @var LoopInterface */
    protected $loop;

    public function __construct($socket)
    {
        $this->socket = $socket;
    }

    public function run(LoopInterface $loop)
    {
        $this->loop = $loop;
        $this->server = $this->createUnixSocket($this->socket, $loop);
        $this->initiateEventHandlers();
    }

    protected function initiateEventHandlers()
    {
        $this->server->on('connection', function (ConnectionInterface $connection) {
            try {
                $processor = new SyslogProcessor(DbFactory::db());
            } catch (\Exception $exception) {
                echo $exception->getMessage();
                $connection->close();
                return;
            }
            $this->log('Got a new connection on my syslog socket');
            $buffer = new BufferedReader($this->loop);
            $buffer->on('line', function ($line) use ($processor) {
                if ($line === '') {
                    $this->log('Ignoring empty line');
                }
                try {
                    $processor->processSyslogLine($line);
                } catch (\Exception $e) {
                    $this->log("Failed to process '$line': " . $e->getMessage());
                    echo $e->getTraceAsString();
                }
            });
            $connection->on('data', function ($data) use ($buffer) {
                $buffer->append($data);
            });
            $connection->on('end', function () {
                $this->log('Connection closed');
            });
        });
    }

    protected function createUnixSocket($uri, $loop)
    {
        if (file_exists($uri)) {
            unlink($uri);
        }

        $old = umask(0000);
        $socket = new UnixServer($uri, $loop);
        umask($old);

        return $socket;
    }

    protected function log($message)
    {
        // TODO.
        echo "$message\n";
    }
}
