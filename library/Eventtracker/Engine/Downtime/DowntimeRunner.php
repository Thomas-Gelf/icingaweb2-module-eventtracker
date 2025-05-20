<?php

namespace Icinga\Module\Eventtracker\Engine\Downtime;

use Icinga\Data\Filter\Filter;
use Icinga\Module\Eventtracker\Daemon\DbBasedComponent;
use Icinga\Module\Eventtracker\Daemon\SimpleDbBasedComponent;
use Icinga\Module\Eventtracker\Engine\EnrichmentHelper;
use Icinga\Module\Eventtracker\Engine\TimePeriod\TimeSlot;
use Icinga\Module\Eventtracker\Issue;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Throwable;

class DowntimeRunner implements DbBasedComponent
{
    use SimpleDbBasedComponent;

    /** @readonly  */
    public DowntimePeriodRunner $periodRunner;
    protected LoggerInterface $logger;
    /** @var array<string, Filter> */
    protected static array $knownFilters = [];
    /** @readonly */
    public ?DowntimeStore $store = null;

    public function __construct(DowntimePeriodRunner $periodRunner, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->periodRunner = $periodRunner;
        $this->periodRunner->on(DowntimePeriodRunner::ON_PERIODS_CHANGED, fn () => $this->recheckOpenIssues());
        $this->periodRunner->on(
            DowntimePeriodRunner::ON_PERIOD_DEACTIVATED,
            function (UuidInterface $uuid, TimeSlot $slot) {
                $this->checkForExpiredDowntime($uuid, $slot);
            }
        );
    }

    protected function checkForExpiredDowntime(UuidInterface $uuid, TimeSlot $slot)
    {
        if (! $this->store) {
            return;
        }

        $inDowntime = $this->store->fetchIssuesInDowntime($uuid);
        $this->logger->notice(sprintf('%d issues should recover', count($inDowntime)));
        foreach ($inDowntime as $issue) {
            if ($rule = $this->getDowntimeRuleForIssueIfAny($issue)) {
                $issue->set('downtime_config_uuid', $rule->get('config_uuid'));
                $this->logger->notice('Not recovering, there is another downtime for this issue');
            } else {
                $rule = $this->periodRunner->getRuleByUuid($uuid);
                $this->store->removeDowntimeForIssue($issue, $rule);
            }
        }
    }

    public function issueShouldBeInDowntime(Issue $issue): bool
    {
        return $this->getDowntimeRuleForIssueIfAny($issue) !==  null;
    }

    public function getDowntimeRuleForIssueIfAny(Issue $issue): ?DowntimeRule
    {
        foreach ($this->periodRunner->getActiveRules() as $rule) {
            if ($this->issueShouldBeInGivenDowntime($issue, $rule)) {
                return $rule;
            }
        }

        return null;
    }

    protected function issueShouldBeInGivenDowntime(Issue $issue, DowntimeRule $downtime): bool
    {
        // TODO: check former downtime reference on issue,
        // when set -> do not allow again, if configured accordingly
        if ($filter = $this->getFilterForDefinition($downtime->get('filter_definition'))) {
            // TODO: use enrichIssueForFilter()?
            return $filter->matches(EnrichmentHelper::getPlainIssue($issue, true));
        }

        if (! $this->store) {
            $this->logger->error(sprintf(
                'Cannot evaluate Downtime rule %s, have no DB',
                $downtime->get('label')
            ));

            return false;
        }

        if ($hostListUuid = $downtime->get('host_list_uuid')) {
            if ($hostList = $this->store->getHostList($hostListUuid)) {
                return $hostList->hasHost($issue->get('host_name'));
            } else {
                $this->logger->error(sprintf(
                    'Referenced host list %s has not been loaded',
                    Uuid::fromBytes($hostListUuid)->toString()
                ));
                return false;
            }
        }

        $this->logger->error(sprintf(
            'Downtime rule %s is invalid, it has neither a filter nor a host list',
            $downtime->get('label')
        ));

        return false;
    }

    /**
     * Checks recent issues against configured (active) Downtime Rules
     *
     * Triggered once we load Downtime Rules from DB
     */
    protected function recheckOpenIssues(): void
    {
        if (! $this->store) {
            return;
        }
        try {
            foreach ($this->store->fetchRecentOpenIssues() as $issue) {
                if ($downtime = $this->getDowntimeRuleForIssueIfAny($issue)) {
                    $this->store->setDowntimeForIssue($issue, $downtime);
                }
            }
        } catch (Throwable $e) {
            $this->logger->error('Rechecking open issues (for Downtimes) failed: ' . $e->getMessage());
        }
    }

    protected function recheckIssuesInDowntime(): void
    {
        if (! $this->store) {
            return;
        }
        try {
            foreach ($this->store->fetchAllIssuesInDowntime() as $issue) {
                if ($configUuid = $issue->get('downtime_config_uuid')) {
                    $rule = $this->periodRunner->getRuleByConfigUuid(Uuid::fromBytes($configUuid));
                } else {
                    $rule = null;
                }
                if ($downtime = $this->getDowntimeRuleForIssueIfAny($issue)) {
                    $this->store->setDowntimeForIssue($issue, $downtime);
                } else {
                    $this->store->removeDowntimeForIssue($issue, $rule);
                }
            }
        } catch (Throwable $e) {
            $this->logger->error('Rechecking open issues (for Downtimes) failed: ' . $e->getMessage());
        }
    }

    protected function getFilterForDefinition(?string $definition): ?Filter
    {
        if ($definition === null    // no filter defined
            || $definition === ''   // just to be safe, not allowed by form
            || $definition === '[]' // no longer required, compat for legacy DB entries
        ) {
            return null;
        }

        return self::$knownFilters[$definition] ?? Filter::fromQueryString($definition);
    }

    protected function onDbReady(): void
    {
        $this->store = new DowntimeStore($this->db, $this->logger);
    }

    protected function onDbLost(): void
    {
        $this->store = null;
    }
}
