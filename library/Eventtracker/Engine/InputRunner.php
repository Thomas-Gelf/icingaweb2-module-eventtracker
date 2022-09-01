<?php

namespace Icinga\Module\Eventtracker\Engine;

use Closure;
use Icinga\Module\Eventtracker\Db\ConfigStore;
use Icinga\Module\Eventtracker\Engine\Input\KafkaInput;
use Icinga\Module\Eventtracker\Issue;
use Icinga\Module\Eventtracker\Web\Widget\IdoDetails;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;

class InputRunner implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public const ON_EVENT = 'event';
    public const ON_ERROR = 'error';

    public const RELOAD_CONFIG_TIMER = 60;

    /** @var LoopInterface */
    protected $loop;

    /** @var ConfigStore */
    protected $store;

    /** @var Input[] */
    protected $inputs = [];

    /** @var Channel[] */
    protected $channels;

    /** @var Action[] */
    protected $actions;

    /** @var TimerInterface */
    protected $reloadConfigTimer;

    public function __construct(ConfigStore $store)
    {
        // TODO: Load all configs from DB. Recheck from time to time. Call "setSettings()" in case
        // they changed. Implementations must reload/restart on their own.
        // This one is about a single Input
        $this->store = $store;

        $this->logger = new NullLogger();
    }

    public function start(LoopInterface $loop)
    {
        $this->loop = $loop;
        $this->inputs = $this->store->loadInputs();
        $this->channels = $this->store->loadChannels();
        $this->actions = $this->store->loadActions(['enabled' => 'y']);
        $this->linkInputsToChannels();
        $this->startInputs($this->loop);
        $this->reloadConfigTimer = $this->startPeriodConfigReload($this->loop);
    }

    public function stop()
    {
        foreach ($this->inputs as $input) {
            $input->stop();
        }

        if ($this->reloadConfigTimer !== null) {
            $this->loop->cancelTimer($this->reloadConfigTimer);

            $this->reloadConfigTimer = null;
        }
    }

    protected function startAction(Action $action, LoopInterface $loop)
    {
        $loop->futureTick(function () use ($action, $loop) {
            $action->on(self::ON_ERROR, function ($error) {
                echo $error->getMessage() . "\n";
                // TODO: log error, detach and restart input
            });
            $action->run($loop);
        });
    }

    protected function startInputs(LoopInterface $loop)
    {
        foreach ($this->inputs as $input) {
            $loop->futureTick(function () use ($input, $loop) {
                $input->on(self::ON_ERROR, function ($error) {
                    echo $error->getMessage() . "\n";
                    // TODO: log error, detach and restart input
                });
                $input->run($loop);
            });
        }

        foreach ($this->actions as $action) {
            $this->startAction($action, $loop);
        }
    }

    protected function startPeriodConfigReload(LoopInterface $loop)
    {
        return $loop->addPeriodicTimer(static::RELOAD_CONFIG_TIMER, function (): void {
            // Load actions without filter for enabled yes,
            // because we want to stop and remove running actions that have been disabled.
            $actions = $this->store->loadActions();

            $create = array_diff_key($actions, $this->actions);
            $update = array_intersect_key($actions, $this->actions);
            $delete = array_diff($this->actions, $actions);

            /** @var Action $action */
            foreach ($create as $k => $action) {
                if (! $action->isEnabled()) {
                    // We load actions without filters, so do not add actions that are disabled.
                    continue;
                }

                $this->startAction($action, $this->loop);
                $this->actions[$k] = $action;
            }

            foreach ($update as $k => $action) {
                if (! $action->isEnabled()) {
                    $delete[$k] = $this->actions[$k];

                    continue;
                }

                $this->actions[$k]
                    ->applySettings($action->getSettings())
                    ->setFilter($action->getFilter());
            }

            foreach ($delete as $k => $action) {
                // $action is from array_diff($this->actions, $actions) from above.
                $action->stop();
                unset($this->actions[$k]);
            }
        });
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
        $db = $this->store->getDb();
        foreach ($this->actions as $action) {
            $filter = $action->getFilter();
            $details = new IdoDetails($issue, $db);
            $object = (object) $issue->getProperties();
            if ($details->hasHost()) {
                $host = $details->getHost();
                foreach ($host->customvars as $varName => $varValue) {
                    $object->{"host.vars.$varName"} = $varValue;
                }
            }

            if (
                $filter !== null
                && ! $action->getFilter()->matches($issue->getProperties())
            ) {
                continue;
            }

            $action->process($issue)->then(null, function ($reason) {
                $this->logger->error($reason);
            });
        }
    }
}
