<?php

namespace Icinga\Module\Eventtracker\Engine\Input;

use Evenement\EventEmitterTrait;
use gipfl\Translation\StaticTranslator;
use Icinga\Module\Eventtracker\Daemon\Application;
use Icinga\Module\Eventtracker\Engine\FormExtension;
use Icinga\Module\Eventtracker\Engine\Input;
use Icinga\Module\Eventtracker\Engine\InputRunner;
use Icinga\Module\Eventtracker\Engine\SettingsProperty;
use Icinga\Module\Eventtracker\Engine\SimpleTaskConstructor;
use Icinga\Module\Eventtracker\Modifier\Settings;
use Icinga\Module\Eventtracker\Web\Form\Input\SyslogFormExtension;
use Icinga\Module\Eventtracker\Stream\BufferedReader;
use Icinga\Module\Eventtracker\Syslog\SyslogParser;
use InvalidArgumentException;
use React\Socket\ConnectionInterface;
use React\Socket\UnixServer;

class SyslogInput extends SimpleTaskConstructor implements Input
{
    use EventEmitterTrait;
    use SettingsProperty;

    protected ?string $socket = null;
    protected ?UnixServer $server = null;

    public function applySettings(Settings $settings)
    {
        // socket_type: unix, udp;
        //   unix -> socket_path
        //   udp -> listening_address, listening_port -> not yet
        switch ($settings->getRequired('socket_type')) {
            case 'udp':
                throw new InvalidArgumentException('UDP Sockets are not supported yet');
            case 'unix':
                $this->socket = $settings->getRequired('socket_path');
                break;
            default:
                throw new InvalidArgumentException(
                    $settings->getRequired('socket_type') . ' is not a valid Syslog socket type'
                );
        }

        $this->setSettings($settings);
    }

    public static function getFormExtension(): FormExtension
    {
        return new SyslogFormExtension();
    }

    public static function getLabel()
    {
        return StaticTranslator::get()->translate('Syslog Receiver');
    }

    public static function getDescription()
    {
        return StaticTranslator::get()->translate(
            'Accepts Syslog on either a UDP or a UNIX socket'
        );
    }

    public function run()
    {
        $this->start();
    }

    public function start()
    {
        if ($this->server) {
            return;
        }

        $this->server = $this->createUnixSocket($this->socket);
        $this->initiateEventHandlers($this->server);
    }

    public function stop()
    {
        if ($this->server) {
            $this->server->close();
            $this->server = null;
        }
        if (file_exists($this->socket)) {
            unlink($this->socket);
        }
    }

    public function pause()
    {
        if ($this->server) {
            $this->server->pause();
        }
    }

    public function resume()
    {
        if ($this->server) {
            $this->server->resume();
        } else {
            $this->start();
        }
    }

    protected function initiateEventHandlers(UnixServer $server)
    {
        $server->on('connection', function (ConnectionInterface $connection) {
            $this->logger->notice('Got a new connection on ' . $this->socket);
            $buffer = new BufferedReader();
            $buffer->on('line', function ($line) {
                // echo "< $line";
                if ($line === '') {
                    return;
                }
                if ($line === '-- MARK --') { // Won't happen. Would it?
                    return;
                }
                try {
                    $event = SyslogParser::parseLine($line);
                    if ($event->message === '-- MARK --') {
                        $this->logger->notice('Got a Syslog MARK');
                        return;
                    }
                    if ($event->object_name !== Application::LOG_NAME
                        || ! isset($event->attributes->syslog_sender_pid)
                        || $event->attributes->syslog_sender_pid !== posix_getpid()
                    ) {
                        $this->emit(InputRunner::ON_EVENT, [$event]);
                    }
                } catch (\Throwable $e) {
                    $this->logger->error("Failed to process '$line': " . $e->getMessage());
                    echo $e->getTraceAsString();
                }
            });
            $connection->pipe($buffer);
            $connection->on('end', function () {
                $this->logger->notice('Syslog connection closed');
            });
        });
        $server->on('error', function ($error) {
            $this->emit(InputRunner::ON_ERROR, [$error]);
        });
    }

    protected function createUnixSocket($uri): UnixServer
    {
        if (file_exists($uri)) {
            $this->logger->warning("Removing orphaned socket '$uri'");
            unlink($uri);
        }

        $old = umask(0000);
        $socket = new UnixServer($uri);
        $this->logger->notice("Listening on '$uri'");
        umask($old);

        return $socket;
    }
}
