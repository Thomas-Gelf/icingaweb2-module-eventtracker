<?php

namespace Icinga\Module\Eventtracker\Engine\Input;

use Evenement\EventEmitterTrait;
use gipfl\Translation\TranslationHelper;
use Icinga\Module\Eventtracker\Engine\SettingsProperty;
use Icinga\Module\Eventtracker\Web\Form\Input\KafkaInputForm;
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

    protected $command = '/usr/bin/kafkacat';

    protected $groupId;

    protected function initialize()
    {
        $settings = $this->getSettings();
        $this->topic = $settings->getRequired('topic');
        $this->groupId = $settings->getRequired('group_id');
        $this->servers = $settings->getRequired('bootstrap_servers');
    }

    public static function getSettingsSubForm()
    {
        return KafkaInputForm::class;
    }

    public static function getLabel()
    {
        return TranslationHelper::getTranslator()->translate('Kafka Consumer');
    }

    public static function getDescription()
    {
        return TranslationHelper::getTranslator()->translate(
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
        $this->process->terminate();
        $this->process = null;
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
        $this->log('Process started');

        $process->on('exit', function ($code, $term) {
            if ($term === null) {
                echo 'exit with code ' . $code . PHP_EOL;
            } else {
                echo 'terminated with signal ' . $term . PHP_EOL;
            }
        });
        $process->stderr->on('data', function ($line) {
            if (substr($line, 0, 2) === '% ') {
                $this->processSpecialLine(substr($line, 2));
            } else {
                echo "ERRO: " . $line . "\n";
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
            $this->log('Ignoring empty line');
            return;
        }

        try {
            // TODO: JSON decoding class
            $this->emit('event', [Json::decode($line)]);
        } catch (\Exception $e) {
            $this->log("Failed to process '$line': " . $e->getMessage());
            echo $e->getTraceAsString();
        }
    }

    protected function processSpecialLine($line) {
        if (substr($line, 0, 7) === 'ERROR: ') {
            throw new RuntimeException(substr($line, 7));
        } elseif (preg_match('/^Reached end of topic (.+?) \[(\d+)] at offset (\d+)$/', $line, $match)) {
            list(, $topic, $partition, $offset) = $match;
            // printf("New offset for %s[%s] is %d\n", $topic, $partition, $offset);
        } else {
            var_dump("UNEXPECTED: $line");
        }
    }

    /**
     * @param string $command
     * @param LoopInterface $loop
     * @return Process
     */
    protected function runCommand($command, LoopInterface $loop)
    {
        $process = new Process($command);
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

    protected function log($message)
    {
        // TODO.
        echo "$message\n";
    }
}
