<?php

namespace Icinga\Module\Eventtracker\Engine;

use Closure;
use Icinga\Module\Eventtracker\Db\ConfigStore;
use Icinga\Module\Eventtracker\Engine\Action\ActionHelper;
use Icinga\Module\Eventtracker\Engine\Downtime\DowntimeRunner;
use Icinga\Module\Eventtracker\Engine\Input\KafkaInput;
use Icinga\Module\Eventtracker\Issue;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\UuidInterface;
use React\EventLoop\Loop;
use React\EventLoop\TimerInterface;
use Throwable;

class InputRunner implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public const ON_EVENT = 'event';
    public const ON_ERROR = 'error';

    public const RELOAD_CONFIG_TIMER = 60;

    protected ConfigStore $store;
    protected DowntimeRunner $downtimeRunner;
    /** @var Input[] */
    protected array $inputs = [];
    /** @var Channel[] */
    protected array $channels = [];
    /** @var Action[] */
    protected array $actions;
    protected ?TimerInterface $reloadConfigTimer = null;
    /** @var array<string, Channel> InputUuid -> Channel */
    protected array $linkedInputChannels = [];

    public function __construct(ConfigStore $store, DowntimeRunner $downtimeRunner, LoggerInterface $logger)
    {
        // TODO: Load all configs from DB. Recheck from time to time. Call "setSettings()" in case
        // they changed. Implementations must reload/restart on their own.
        // This one is about a single Input
        $this->store = $store;
        $this->downtimeRunner = $downtimeRunner;
        $this->logger = $logger;
    }

    public function start()
    {
        try {
            $this->inputs = $this->store->loadInputs();
            $buckets = $this->store->loadBuckets();
            $this->channels = $this->store->loadChannels($this->downtimeRunner, $buckets);
            $this->actions = $this->store->loadActions(['enabled' => 'y']);
            $this->linkInputsToChannels();
            $this->startInputs();
            $this->reloadConfigTimer = $this->startPeriodConfigReload();
        } catch (Throwable $e) {
            $this->logger->error($e->getMessage());
        }
    }

    public function getOptionalInputChannel(UuidInterface $uuid): ?Channel
    {
        return $this->linkedInputChannels[$uuid->toString()] ?? null;
    }

    public function stop()
    {
        foreach ($this->inputs as $input) {
            $input->stop();
        }

        if ($this->reloadConfigTimer !== null) {
            Loop::cancelTimer($this->reloadConfigTimer);

            $this->reloadConfigTimer = null;
        }
    }

    protected function startAction(Action $action)
    {
        Loop::futureTick(function () use ($action) {
            $action->on(self::ON_ERROR, function (Throwable $error) use ($action) {
                $this->logger->error('Starting Action failed, will be disabled: ' . $error->getMessage());
                unset($this->actions[$action->getUuid()->toString()]);
            });
            $action->run();
        });
    }

    protected function startInputs()
    {
        foreach ($this->inputs as $input) {
            Loop::futureTick(function () use ($input) {
                $input->on(self::ON_ERROR, function ($error) use ($input) {
                    $this->logger->error($input->getName() . ' failed: ' . $error->getMessage());
                    // TODO: log error, detach and restart input
                });
                $input->run();
            });
        }

        foreach ($this->actions as $action) {
            $this->startAction($action);
        }
    }

    protected function startPeriodConfigReload(): TimerInterface
    {
        return Loop::addPeriodicTimer(static::RELOAD_CONFIG_TIMER, function (): void {
            // Load actions without filter for enabled yes,
            // because we want to stop and remove running actions that have been disabled.
            $actions = $this->store->loadActions();

            $create = array_diff_key($actions, $this->actions);
            $update = array_intersect_key($actions, $this->actions);
            $delete = array_diff_key($this->actions, $actions);

            /** @var Action $action */
            foreach ($create as $k => $action) {
                if (! $action->isEnabled()) {
                    // We load actions without filters, so do not add actions that are disabled.
                    continue;
                }

                $this->startAction($action);
                $this->actions[$k] = $action;
            }

            foreach ($update as $k => $action) {
                if (! $action->isEnabled()) {
                    $delete[$k] = $this->actions[$k];

                    continue;
                }

                $this->actions[$k]->setFilter($action->getFilter());
                $this->actions[$k]->applySettings($action->getSettings());
            }

            foreach ($delete as $k => $action) {
                // $action is from array_diff_key($this->actions, $actions) from above.
                $action->stop();
                unset($this->actions[$k]);
            }
        });
    }

    protected function linkInputsToChannels()
    {
        $this->linkedInputChannels = [];
        foreach ($this->channels as $channel) {
            $channel->setDaemonized();
            $channel->on(Channel::ON_ISSUE, Closure::fromCallable([$this, 'onIssue']));

            foreach ($this->inputs as $input) {
                if ($channel->wantsInput($input)) {
                    if ($input instanceof KafkaInput) {
                        $channel->addInput($input, true);
                    } else {
                        $channel->addInput($input);
                    }
                    $this->linkedInputChannels[$input->getUuid()->toString()] = $channel;
                }
            }
        }
    }

    public function onIssue(Issue $issue): void
    {
        Loop::futureTick(fn () => ActionHelper::processIssue(
            $this->actions,
            $issue,
            $this->store->getDb(),
            $this->logger
        )->otherwise(function (Throwable $reason) {
            $this->logger->error($reason);
        }));
    }
}
