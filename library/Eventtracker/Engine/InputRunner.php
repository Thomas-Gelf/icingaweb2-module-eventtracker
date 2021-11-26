<?php

namespace Icinga\Module\Eventtracker\Engine;

use Icinga\Module\Eventtracker\Db\ConfigStore;
use Icinga\Module\Eventtracker\Engine\Input\KafkaInput;
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

    public function __construct(ConfigStore $store)
    {
        // TODO: Load all configs from DB. Recheck from time to time. Call "setSettings()" in case
        // they changed. Implementations must reload/restart on their own.
        // This one is about a single Input
        $this->store = $store;
    }

    public function start(LoopInterface $loop)
    {
        $this->loop = $loop;
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
                    if ($input instanceof KafkaInput) {
                        $channel->addInput($input, true);
                    } else {
                        $channel->addInput($input);
                    }
                }
            }
        }
    }
}
