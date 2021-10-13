<?php

namespace Icinga\Module\Eventtracker\Engine;

use Icinga\Module\Eventtracker\Db\ConfigStore;
use React\EventLoop\LoopInterface;

class InputRunner
{
    /** @var LoopInterface */
    protected $loop;

    /** @var ConfigStore */
    protected $store;

    /** @var Input[] */
    protected $inputs;

    /** @var Channel[] */
    protected $channels;

    public function __construct(LoopInterface $loop, ConfigStore $store)
    {
        // TODO: Load all configs from DB. Recheck from time to time. Call "setSettings()" in case
        // they changed. Implementations must reload/restart on their own.
        // This one is about a single Input
        $this->loop = $loop;
        $this->store = $store;
    }

    public function start()
    {
        $this->inputs = $this->store->loadInputs();
        $this->channels = $this->store->loadChannels();
        $this->linkInputsToChannels();
        $this->startInputs($this->loop);
    }

    public function stop()
    {
        foreach ($this->inputs as $input) {
            $input->stop();
        }
    }

    protected function startInputs(LoopInterface $loop)
    {
        foreach ($this->inputs as $input) {
            $loop->futureTick(function () use ($input, $loop) {
                $input->on('error', function ($error) {
                    echo $error->getMessage() . "\n";
                    // TODO: log error, detach and restart input
                });
                $input->run($loop);
            });
        }
    }

    protected function linkInputsToChannels()
    {
        foreach ($this->channels as $channel) {
            foreach ($this->inputs as $input) {
                if ($channel->wantsInput($input)) {
                    $channel->addInput($input);
                }
            }
        }
    }
}
