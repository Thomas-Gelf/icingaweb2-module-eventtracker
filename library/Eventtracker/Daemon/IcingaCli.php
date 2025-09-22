<?php

namespace Icinga\Module\Eventtracker\Daemon;

use Evenement\EventEmitterTrait;
use gipfl\Process\FinishedProcessState;
use React\Promise\Deferred;

class IcingaCli
{
    use EventEmitterTrait;

    protected ?IcingaCliRunner $runner;
    protected array $arguments = [];

    public function __construct(?IcingaCliRunner $runner = null)
    {
        if ($runner === null) {
            $runner = IcingaCliRunner::forArgv();
        }
        $this->runner = $runner;
        $this->init();
    }

    protected function init()
    {
        // Override this if you want.
    }

    public function setArguments(array $arguments)
    {
        $this->arguments = $arguments;

        return $this;
    }

    public function run()
    {
        $process = $this->runner->command($this->arguments);
        $canceller = function () use ($process) {
            // TODO: first soft, then hard
            $process->terminate();
        };
        $deferred = new Deferred($canceller);

        $process->on('exit', function ($exitCode, $termSignal) use ($deferred) {
            $state = new FinishedProcessState($exitCode, $termSignal);
            if ($state->succeeded()) {
                $deferred->resolve(null);
            } else {
                $deferred->reject(new \RuntimeException($state->getReason()));
            }
        });
        $process->start();
        $this->emit('start', [$process]);

        return $deferred->promise();
    }
}
