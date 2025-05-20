<?php

namespace Icinga\Module\Eventtracker\Engine\Input;

use Evenement\EventEmitterTrait;
use gipfl\Json\JsonString;
use gipfl\Translation\StaticTranslator;
use Icinga\Module\Eventtracker\Engine\FormExtension;
use Icinga\Module\Eventtracker\Engine\Input;
use Icinga\Module\Eventtracker\Engine\InputRunner;
use Icinga\Module\Eventtracker\Engine\SettingsProperty;
use Icinga\Module\Eventtracker\Engine\SimpleTaskConstructor;
use Icinga\Module\Eventtracker\Modifier\Settings;
use Icinga\Module\Eventtracker\Web\Form\Input\KafkaFormExtension;
use Icinga\Module\Eventtracker\Stream\BufferedReader;
use React\ChildProcess\Process;
use React\EventLoop\Loop;
use RuntimeException;

class KafkaInput extends SimpleTaskConstructor implements Input
{
    use EventEmitterTrait;
    use SettingsProperty;

    protected ?Process $process = null;
    protected ?string $topic = null;
    protected ?string $servers = null;
    protected ?string $command = null;
    protected ?string $groupId; // Consumer Group ID
    protected bool $stopping = false;

    public function applySettings(Settings $settings)
    {
        $this->topic = $settings->getRequired('topic');
        $this->groupId = $settings->getRequired('group_id');
        $this->servers = $settings->getRequired('bootstrap_servers');
        $this->command = $settings->getRequired('kcat_binary');

        $this->setSettings($settings);
    }

    public static function getFormExtension(): FormExtension
    {
        return new KafkaFormExtension();
    }

    public static function getLabel()
    {
        return StaticTranslator::get()->translate('Kafka Consumer');
    }

    public static function getDescription()
    {
        return StaticTranslator::get()->translate(
            'Consumes a Kafka Topic'
        );
    }

    public function run()
    {
        $this->start();
    }

    public function start()
    {
        if ($this->process) {
            return;
        }

        $this->process = $this->runCommand($this->prepareCommandString());
        $this->initiateEventHandlers($this->process);
    }

    public function stop()
    {
        if ($this->process) {
            $this->stopping = true;
            $this->process->terminate();
            $this->process = null;
        }
    }

    public function pause()
    {
        if ($this->process) {
            $this->process->stdout->pause();
            $this->process->stderr->pause();
        }
    }

    public function resume()
    {
        if ($this->process) {
            $this->process->stdout->resume();
            $this->process->stderr->resume();
        } else {
            $this->start();
        }
    }

    protected function initiateEventHandlers(Process $process)
    {
        $process->on('exit', function ($code, $term) {
            $this->process = null;
            if ($this->stopping) {
                $this->stopping = false;
                if ($term === null) {
                    $this->logger->debug($this->command . ' exit with code ' . $code);
                } else {
                    $this->logger->warning($this->command . ' terminated with signal ' . $term);
                }
            } else {
                if ($term === null) {
                    $this->logger->warning($this->command . ' exit with code ' . $code);
                } else {
                    $this->logger->error($this->command . ' terminated with signal ' . $term);
                }
                $reconnectTimeout = 30;
                $this->logger->info("Scheduling reconnection in {$reconnectTimeout}s");
                Loop::addTimer($reconnectTimeout, fn () => $this->start());
            }
        });
        $process->stderr->on('data', function ($line) {
            if (substr($line, 0, 2) === '% ') {
                $this->processSpecialLine(substr($line, 2));
            } else {
                $this->logger->error("STDERR: $line");
            }
        });
        $reader = new BufferedReader();
        $reader->on('line', function ($line) {
            if (substr($line, 0, 2) === '% ') {
                $this->processSpecialLine(substr($line, 2));
            } else {
                $this->processLine($line);
            }
        });
        $process->stdout->pipe($reader);
    }

    protected function processLine($line)
    {
        if ($line === '') {
            $this->logger->info('Ignoring empty line');
            return;
        }

        try {
            $this->emit(InputRunner::ON_EVENT, [JsonString::decode($line)]);
        } catch (\Exception $e) {
            $this->logger->error(sprintf("Kafka Input failed with %s, processing: %s", $e->getMessage(), $line));
        }
    }

    protected function processSpecialLine($line)
    {
        if (substr($line, 0, 7) === 'ERROR: ') {
            $this->emit(InputRunner::ON_ERROR, [new RuntimeException(rtrim(substr($line, 7)))]);
        } elseif (preg_match('/^Reached end of topic (.+?) \[(\d+)] at offset (\d+)$/', $line, $match)) {
            list(, $topic, $partition, $offset) = $match;
            // printf("New offset for %s[%s] is %d\n", $topic, $partition, $offset);
        } else {
            $this->logger->error("UNEXPECTED line: $line");
        }
    }

    protected function runCommand(string $command): Process
    {
        $this->logger->info("Launching $command");
        $process = new Process("exec $command");
        $process->start();

        return $process;
    }

    protected function addExtraParam(&$params, $key, $value)
    {
        $params[] = '-X';
        $params[] = "$key=$value";
    }

    protected function addExtraParams(&$params, $extra)
    {
        foreach ($extra as $key => $value) {
            $this->addExtraParam($params, $key, $value);
        }
    }

    public function prepareCommandString(): string
    {
        // TODO: We might want to write our settings to a temporary properties file.
        $params = [
            // '-C',
            // '-t', $this->topic,
            // '-o', $offset, // beginning, end, stored, numeric, negative num
            // '-f', 'Topic %t[%p], offset: %o, key: %k, Timestamp: %T \n',
            // unbuffered stdout, removing this leads to issues which are terrible to
            // debug: https://github.com/edenhill/kcat/issues/3
            '-u',
            '-G', $this->groupId, $this->topic,
            '-q', // Quiet, as we do not need to parse position with groups
            '-b', $this->servers,

            // '-e', exit after last message
        ];
        $this->addExtraParam($params, 'client.id', 'Icinga Eventtracker');

        $settings = $this->getSettings();
        if ($settings->get('transport_encryption') === 'ssl') {
            $this->addExtraParam($params, 'security.protocol', 'ssl');
        }
        if ($caCert = $settings->get('ca_certificate')) {
            $this->addExtraParam($params, 'ssl.ca.location', $caCert);
        }
        switch ($settings->get('authentication')) {
            case 'sasl':
                $this->addExtraParams($params, [
                    'sasl.username' => $settings->getRequired('username'),
                    'sasl.password' => $settings->getRequired('password'),
                ]);
                break;
            case 'ssl':
                $this->addExtraParams($params, [
                    'ssl.certificate.location' => $settings->getRequired('client_certificate_file'),
                    'ssl.key.location'         => $settings->getRequired('client_key_file'),
                    // ssl.key.password, ciphers...
                ]);
                break;
            default:
            // Nothing to do
        }

        $command = escapeshellarg($this->command);
        foreach ($params as $param) {
            $command .= ' ' . $this->escapeArgument($param);
        }

        return $command;
    }

    protected function escapeArgument($argument): string
    {
        if (preg_match('/^-[A-Za-z]$/', $argument)) {
            return $argument;
        }
        if (preg_match('/^--[A-Za-z]$/', $argument)) {
            return $argument;
        }

        return escapeshellarg($argument);
    }
}
