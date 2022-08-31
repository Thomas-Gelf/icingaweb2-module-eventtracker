<?php

namespace Icinga\Module\Eventtracker\Engine\Action;

use Evenement\EventEmitterTrait;
use Exception;
use gipfl\Process\FinishedProcessState;
use gipfl\Process\ProcessKiller;
use gipfl\Translation\StaticTranslator;
use Icinga\Module\Eventtracker\ConfigHelper;
use Icinga\Module\Eventtracker\Engine\Action;
use Icinga\Module\Eventtracker\Engine\FormExtension;
use Icinga\Module\Eventtracker\Engine\SettingsProperty;
use Icinga\Module\Eventtracker\Engine\SimpleTaskConstructor;
use Icinga\Module\Eventtracker\Issue;
use Icinga\Module\Eventtracker\Web\Form\Action\CommandFormExtension;
use React\ChildProcess\Process;
use React\EventLoop\LoopInterface;
use React\Promise\CancellablePromiseInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use SplObjectStorage;
use function React\Promise\resolve;

class CommandAction extends SimpleTaskConstructor implements Action
{
    use ActionProperties;
    use EventEmitterTrait;
    use SettingsProperty;

    /** @var LoopInterface */
    protected $loop;

    /** @var string */
    protected $command;

    /** @var SplObjectStorage */
    protected $promises;

    protected $paused = true;

    protected function initialize()
    {
        $settings = $this->getSettings();
        $this->command = $settings->getRequired('command');
        $this->promises = new SplObjectStorage();
    }

    public static function getFormExtension(): FormExtension
    {
        return new CommandFormExtension();
    }

    public static function getLabel()
    {
        return StaticTranslator::get()->translate('Command');
    }

    public static function getDescription()
    {
        return StaticTranslator::get()->translate(
            'Execute a command'
        );
    }

    public function run(LoopInterface $loop)
    {
        $this->loop = $loop;
        $this->start();
    }

    public function start()
    {
        $this->resume();
    }

    public function stop()
    {
        $this->pause();

        /** @var CancellablePromiseInterface $promise */
        foreach ($this->promises as $promise) {
            $promise->cancel();
        }
    }

    public function pause()
    {
        $this->paused = true;
    }

    public function resume()
    {
        $this->paused = false;
    }

    public function process(Issue $issue): PromiseInterface
    {
        return $this->command($issue);
    }

    protected function command(Issue $issue): PromiseInterface
    {
        if ($this->paused) {
            $this->logger->info('Not executing command, action has been paused');

            return resolve();
        }

        $process = new Process(ConfigHelper::fillPlaceholders($this->command, $issue, '\escapeshellarg'));

        $deferred = new Deferred(function () use ($process) {
            ProcessKiller::terminateProcess($process, $this->loop);
        });
        $promise = $deferred->promise();
        $this->promises->attach($promise);
        $promise->always(function () use ($promise) {
            $this->promises->detach($promise);
        });

        $process->start($this->loop);
        $process->stdout->on('data', function ($chunk) {
            // TODO: Logging
        });
        $process->on('exit', function ($exitCode, $termSignal) use ($deferred) {
            // TODO: Logging
            $state = new FinishedProcessState($exitCode, $termSignal);
            if ($state->succeeded()) {
                $deferred->resolve();
            } else {
                $deferred->reject(new Exception($state->getReason()));
            }
        });

        return $promise;
    }
}
