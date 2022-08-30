<?php

namespace Icinga\Module\Eventtracker\Engine\Input;

use Evenement\EventEmitterTrait;
use gipfl\Translation\StaticTranslator;
use Icinga\Module\Eventtracker\Engine\SettingsProperty;
use Icinga\Module\Eventtracker\Web\Form\Input\KafkaFormExtension;
use Icinga\Module\Eventtracker\Stream\BufferedReader;
use Icinga\Util\Json;
use React\ChildProcess\Process;
use React\EventLoop\LoopInterface;
use RuntimeException;

class KafkaInput extends SimpleInputConstructor
{
    use EventEmitterTrait;
    use SettingsProperty;

    /** @var Process */
    protected $process;

    /** @var LoopInterface */
    protected $loop;

    /** @var string */
    protected $topic;

    /** @var string */
    protected $servers;

    /** @var string */
    protected $command;

    /** @var string Consumer Group ID */
    protected $groupId;

    protected $stopping = false;

    protected function initialize()
    {
        $settings = $this->getSettings();
        $this->topic = $settings->getRequired('topic');
        $this->groupId = $settings->getRequired('group_id');
        $this->servers = $settings->getRequired('bootstrap_servers');
        $this->command = $settings->getRequired('kcat_binary');
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

    public function setCommand($command)
    {
        $this->command = $command;
    }

    public function run(LoopInterface $loop)
    {
        $this->loop = $loop;
        $this->start();
    }

    public function start()
    {
        if ($this->process) {
            return;
        }

        $this->process = $this->runCommand($this->prepareCommandString(), $this->loop);
        $this->initiateEventHandlers($this->process, $this->loop);
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

    protected function initiateEventHandlers(Process $process, LoopInterface $loop)
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
                $this->logger->info('Scheduling reconnection in 10s');
                $this->loop->addTimer(10, function () {
                    $this->start();
                });
            }
        });
        $process->stderr->on('data', function ($line) {
            if (substr($line, 0, 2) === '% ') {
                $this->processSpecialLine(substr($line, 2));
            } else {
                $this->logger->error("STDERR: $line");
            }
        });
        $reader = new BufferedReader($loop);
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
            // TODO: JSON decoding class
            $this->emit('event', [Json::decode($line)]);
        } catch (\Exception $e) {
            $this->logger->error("Failed to process '$line': " . $e->getMessage());
        }
    }

    protected function processSpecialLine($line)
    {
        if (substr($line, 0, 7) === 'ERROR: ') {
            $this->emit('error', [new RuntimeException(rtrim(substr($line, 7)))]);
        } elseif (preg_match('/^Reached end of topic (.+?) \[(\d+)] at offset (\d+)$/', $line, $match)) {
            list(, $topic, $partition, $offset) = $match;
            // printf("New offset for %s[%s] is %d\n", $topic, $partition, $offset);
        } else {
            $this->logger->error("UNEXPECTED line: $line");
        }
    }

    /**
     * @param string $command
     * @param LoopInterface $loop
     * @return Process
     */
    protected function runCommand($command, LoopInterface $loop)
    {
        $this->logger->info("Launching $command");
        $process = new Process("exec $command");
        $process->start($loop);

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

    protected function prepareCommandString()
    {
        // TODO: We might want to write our settings to a temporary properties file.
        $params = [
            // '-C',
            // '-t', $this->topic,
            // '-o', $offset, // beginning, end, stored, numeric, negative num
            // '-f', 'Topic %t[%p], offset: %o, key: %k, Timestamp: %T \n',

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
            $command .= ' ' . escapeshellarg($param);
        }

        return $command;
    }
}
