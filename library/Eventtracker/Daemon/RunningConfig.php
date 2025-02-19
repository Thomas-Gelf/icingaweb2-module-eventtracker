<?php

namespace Icinga\Module\Eventtracker\Daemon;

use Closure;
use Icinga\Module\Eventtracker\Engine\Downtime\DowntimeRule;
use Psr\Log\LoggerInterface;
use React\EventLoop\Loop;

class RunningConfig implements DbBasedComponent
{
    use SimpleDbBasedComponent;

    /** @var LoggerInterface */
    protected $logger;

    /** @var DowntimeRule[] */
    protected $downtimeRules = [];

    /** @var Closure[] */
    protected $onRuleChanges;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function onDbReady()
    {
        $this->reloadDowntimeRules();
    }

    public function reloadDowntimeRules(): ?bool
    {
        // Disabled for now
        return null;

        if ($this->db === null) {
            return null;
        }
        $this->logger->notice('Reloading Downtime Rules');

        $hasChanges = false;
        $newIds = [];
        foreach (DowntimeRule::loadAll($this->dbStore) as $rule) {
            $id = $rule->get('config_uuid');
            $newIds[$id] = $id;
            if (! isset($this->downtimeRules[$id])) {
                $this->downtimeRules[$id] = $rule;
                $hasChanges = true;
            }
        }
        foreach (array_keys($this->downtimeRules) as $id) {
            if (! isset($newIds[$id])) {
                unset($this->downtimeRules[$id]);
                $hasChanges = true;
            }
        }
        if ($hasChanges) {
            $this->logger->notice('Downtime configuration change detected, triggering recalculation');
            Loop::get()->futureTick(function () {
                foreach ($this->onRuleChanges as $callback) {
                    $callback($this->downtimeRules);
                }
            });
        }

        return $hasChanges;
    }

    public function watchRules(Closure $callback)
    {
        $this->onRuleChanges[] = $callback;
    }
}
