<?php

namespace Icinga\Module\Eventtracker\Engine;

use Closure;
use Icinga\Module\Eventtracker\Db\ConfigStore;
use Icinga\Module\Eventtracker\Engine\Input\KafkaInput;
use Icinga\Module\Eventtracker\Issue;
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

    /** @var Action[] */
    protected $actions;

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
        $this->actions = $this->store->loadActions(['enabled' => 'y']);
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

        foreach ($this->actions as $action) {
            $loop->futureTick(function () use ($action, $loop) {
                $action->on('error', function ($error) {
                    echo $error->getMessage() . "\n";
                    // TODO: log error, detach and restart input
                });
                $action->run($loop);
            });
        }
    }

    protected function linkInputsToChannels()
    {
        foreach ($this->channels as $channel) {
            $channel->on(Channel::ON_ISSUE, Closure::fromCallable([$this, 'onIssue']));

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

    public function onIssue(Issue $issue): void
    {
        foreach ($this->actions as $action) {
            $filter = $action->getFilter();
            if (
                $filter !== null
                && ! $action->getFilter()->matches($issue->getProperties())
            ) {
                continue;
            }

            $action->process($issue);
        }
    }
}
