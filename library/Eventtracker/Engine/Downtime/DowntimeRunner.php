<?php

namespace Icinga\Module\Eventtracker\Engine\Downtime;

use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;
use gipfl\ZfDb\Adapter\Adapter;
use gipfl\ZfDb\Select;
use Icinga\Data\Filter\Filter;
use Icinga\Module\Eventtracker\Daemon\DbBasedComponent;
use Icinga\Module\Eventtracker\Daemon\SimpleDbBasedComponent;
use Icinga\Module\Eventtracker\Engine\EnrichmentHelper;
use Icinga\Module\Eventtracker\Issue;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use React\EventLoop\Loop;
use React\EventLoop\TimerInterface;

class DowntimeRunner implements EventEmitterInterface, DbBasedComponent
{
    use EventEmitterTrait;
    use SimpleDbBasedComponent;

    const ON_ERROR = 'error';
    const LOOK_AHEAD_MS = 600000; // 10 minutes

    /** @var ?TimerInterface */
    protected $nextCheck = null;
    /** @var DowntimeRule[] */
    protected $activeDowntimes = [];
    /** @var HostList[] */
    protected $hostLists = [];
    /** @var LoggerInterface */
    protected $logger;
    /** @var int */
    protected $currentTime;
    /** @var DowntimeRule[] */
    protected $allDowntimeRules = [];

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function initDb(Adapter $db): void
    {
        $this->db = $db;
        $this->scheduleNextCheck();
    }

    protected function getHostList(string $binaryUuid): ?HostList
    {
        if (!isset($this->hostLists[$binaryUuid])) {
            if ($this->db) {
                if ($list = HostList::load(Uuid::fromBytes($binaryUuid), $this->db)) {
                    $this->hostLists[$binaryUuid] = $list;
                }
            }
        }

        return $this->hostLists[$binaryUuid] ?? null;
    }

    public function setDowntimeRules(array $rules)
    {
        $this->allDowntimeRules = $rules;

        $this->recheckOpenIssues();
    }

    public function issueShouldBeInDowntime(Issue $issue): bool
    {
        $logUuid = $issue->getNiceUuid();
        foreach ($this->activeDowntimes as $downtime) {
            if ($filter = $downtime->get('filter_definition')) {
                $filter = Filter::fromQueryString($filter);
                // TODO: use enrichIssueForFilter()?
                if (! $filter->matches(EnrichmentHelper::getPlainIssue($issue, true))) {
                    $this->logger->debug("Issue $logUuid ignored for this downtime, it does not match " . $filter);
                    continue;
                }
                if ($hostListUuid = $downtime->get('hostlist_uuid')) {
                    if (! isset($this->hostLists[$hostListUuid])) {
                        $this->logger->error(sprintf(
                            'Referenced host list %s has not been loaded',
                            Uuid::fromBytes($hostListUuid)->toString()
                        ));
                        continue;
                    }
                    if (! $this->hostLists[$hostListUuid]->hasHost($issue->get('host_name'))) {
                        $this->logger->debug("Issue $logUuid ignored, host not in list: " . $issue->get('host_name'));
                        continue;
                    }
                }
            }

            return true;
        }

        return false;
    }

    protected function recheckOpenIssues()
    {
        $this->logger->notice('XXXx ---- rechecking open issues'. count($this->activeDowntimes));
        foreach ($this->fetchOpenIssues() as $issue) {
            foreach ($this->activeDowntimes as $rule) {
                $label = $rule->get('label');
                $filter = $rule->get('filter_definition');
                if ($filter && $filter !== '[]') { // Why [] ??
                    $filter = Filter::fromQueryString($filter);
                    if ($filter->matches($issue)) {
                        $this->logger->notice(sprintf('%s, Filter: XXXXXXXXX %s should be in Downtime', $label, $issue->getNiceUuid()));
                    } else {
                        // $this->logger->notice(sprintf('%s, Filter: XXXXXXXXX %s should NOT be in Downtime', $label, $issue->getNiceUuid()));
                    }
                } elseif ($hostListUuid = $rule->get('host_list_uuid')) {
                    if ($list = $this->getHostList($hostListUuid)) {
                        $hostName = $issue->get('host_name');
                        if ($hostName !== null && $list->hasHost($hostName)) {
                            $this->logger->notice(sprintf('%s, Host list: XXXXXXXXX %s should be in Downtime', $label, $issue->getNiceUuid()));
                            $issue->set('status', 'in_downtime');
                            $issue->storeToDb($this->db);
                            /*
                            $this->db->insert('downtime_affected_issue', [
                                'calculation_uuid' => , // Calculation UUID?
                                'issue_uuid' => $issue->get('issue_uuid'),
                                'ts_triggered' => 'y', // VERGESSEN. Vielleicht besser beim Starten der Downtime gleich reinsetzen? Also in "affected"`?
                                'ts_scheduled_end' => ,// Calculation-> ts_scheduled_end ??? (hab nicht nachgeschaut)
                                'assignment' => 'rule',
                                'assigned_by' => null,
                                'author' => null,
                            ]);
                            */
                        } else {
                            // $this->logger->notice(sprintf('%s, Host list: XXXXXXXXX %s should NOT be in Downtime', $label, $issue->getNiceUuid()));
                        }
                    } else {
                        // $this->logger->notice('Host list missing (or hostname === null): ' . Uuid::fromBytes($hostListUuid)->toString());
                    }
                } else {
                    // $this->logger->notice(sprintf('%s All hosts? XXXXXXXXX %s should be in Downtime', $label, $issue->getNiceUuid()));
                }
            }
        }
    }

    /**
     * @return Issue[]
     */
    protected function fetchOpenIssues(): array
    {
        $db = $this->db;
        $rows = $db->fetchAll($db->select()->from('issue')->where('status = ?', 'open'));
        $issues = [];
        foreach ($rows as $row) {
            $issue = Issue::fromSerialization($row);
            $issue->setStored();
            $issues[] = $issue;
        }

        return $issues;
    }

    /**
     * @return DowntimeCalculated[]
     */
    protected function fetchFormerlyActive(): array
    {
        $this->logger->debug('Fetching formerly active');
        return array_merge($this->fetchFinished(), $this->fetchLost());
    }

    protected function scheduleNextCheck()
    {
        $loop = Loop::get();
        if ($this->nextCheck) {
            $loop->cancelTimer($this->nextCheck);
            $this->nextCheck = null;
        }
        $this->nextCheck = $loop->addTimer(0.01, function () {
            $this->checkNow();
        });
    }

    protected function checkNow()
    {
        $this->currentTime = time() * 1000;
        try {
            $finished = $this->fetchFormerlyActive();
            $finishedIssues = [];
            if (! empty($finished)) {
                $finishedIssueUuids = [];
                $this->logger->debug('Got some finished: ' . print_r($finished, 1));
                foreach ($finished as $finishedDowntimeCalculated) {
                    foreach ($this->db->fetchCol($this->selectAffectedIssues($finishedDowntimeCalculated)) as $uuid) {
                        $finishedIssueUuids[$uuid] = $uuid; // catch duplicates
                    }
                }
                foreach ($finishedIssueUuids as $uuid) {
                    $finishedIssues[$uuid] = Issue::load($uuid, $this->db);
                }
            }
            $next = $this->fetchNextIterations();
            foreach ($next as $n) {
                $ruleConfigUuid = $n->get('rule_config_uuid');
                $active = $this->activeDowntimes;
                $this->activeDowntimes = [];
                $this->activeDowntimes[$ruleConfigUuid] = $active[$ruleConfigUuid]
                    ?? DowntimeRule::loadByConfigUuid($ruleConfigUuid, $this->db);
                $this->logger->notice('Next: ' . Uuid::fromBytes($n->get('rule_config_uuid'))->toString() . print_r($n->getProperties(), 1));
                $this->db->update(DowntimeCalculated::TABLE_NAME, [
                        'is_active' => 'y',
                        'ts_started' => $this->currentTime
                    ], $this->db->quoteInto('rule_config_uuid = ?', $ruleConfigUuid));
            }
            $this->reTriggerIssuesFromFinishedDowntimes($finishedIssues);

        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());
            $this->emit(self::ON_ERROR, [$e]);
        }
    }

    protected function selectAllOpenIssues()
    {
        // foreach ($this->db->fetchAll($this->db->select()->from(Issue::TABLE_NAME)->))
    }

    protected function selectAffectedIssues(DowntimeCalculated $downtimeCalculated): Select
    {
        return $this->db->select()->from(['dc' => 'downtime_calculated'], [])
            ->join(['dai' => 'downtime_affected_issue'], 'dc.uuid = dai.calculation_uuid', 'issue_uuid')
            ->where('dc.uuid = ?', $downtimeCalculated->get('uuid'));
    }

    /**
     * @param DowntimeCalculated[] $next
     * @param DowntimeCalculated[] $finished
     * @return void
     */
    protected function switchActiveDowntimes(array $next, array $finished): void
    {
        $finishedIssueUuids = [];
        foreach ($finished as $finishedDowntimeCalculated) {
            foreach ($this->db->fetchCol($this->selectAffectedIssues($finishedDowntimeCalculated)) as $uuid) {
                $finishedIssueUuids[$uuid] = $uuid; // catch duplicates
            }
        }
        $finishedIssues = [];
        foreach ($finishedIssueUuids as $uuid) {
            $finishedIssues[$uuid] = Issue::load($uuid, $this->db);
        }
        $this->activeDowntimes = [];
        foreach ($next as $nextDowntimeCalculated) {
            $configUuid = $nextDowntimeCalculated->get('rule_config_uuid');
            /** @var DowntimeRule $rule */
            $this->activeDowntimes[$configUuid] = DowntimeRule::load($this->dbStore, $configUuid);
        }
        $this->processFinished($finishedIssues);
    }

    /**
     * @param Issue[] $issues
     * @return void
     */
    protected function reTriggerIssuesFromFinishedDowntimes(array $issues)
    {
        $reTrigger = [];
        foreach ($issues as $issue) {
            if ($this->issueShouldBeInDowntime($issue)) {
                continue;
            }

            $reTrigger[$issue->getUuid()] = $issue;
        }

        foreach ($reTrigger as $issue) {
            $issue->set('status', 'open');
            $issue->storeToDb($this->db);
        }
    }

    /**
     * @param DowntimeCalculated[] $calculated
     */
    protected function processFinished(array $calculated)
    {
        foreach ($calculated as $calculation) {
            $recovered = $this->loadIssuesForCalculation($calculation);
            foreach ($recovered as $issue) {
                if (! $this->issueShouldBeInDowntime($issue)) {
                    $issue->set('status', 'open');
                    $issue->storeToDb($this->db);
                    $this->logger->debug('Issue is being reopened, downtime finished');
                } else {
                    $this->logger->debug('Issue is covered by another downtime');
                }
            }
        }
    }

    /**
     * @return Issue[]
     */
    protected function loadIssuesForCalculation(DowntimeCalculated $calculation): array
    {
        $query = $this->db->select()
            ->from(['i' => 'issue'], '*')
            ->join(['dai' => 'downtime_affected_issue'], 'i.uuid = dai.issue_uuid', [])
            ->where('dai.uuid = ?', $calculation->get('uuid'));
        $issues = [];
        foreach ($this->db->fetchAll($query) as $row) {
            $issue = Issue::fromSerialization($row);
            $issue->setStored();
            $issues[] = $issue;
        }

        return $issues;
    }

    /**
     * @return DowntimeCalculated[]
     */
    protected function fetchNextIterations(): array
    {
        $query = $this->selectDowntimes()
            ->where('dc.ts_expected_start < ?', $this->currentTime + self::LOOK_AHEAD_MS)
            ->where('dc.ts_expected_end > ?', $this->currentTime)
            ->where('dc.is_active = ?', 'n');

        return $this->fetchCalculatedDowntimes($query);
    }

    /**
     * Active (calculated) Downtimes
     *
     * @return DowntimeCalculated[]
     */
    protected function fetchActive(): array
    {
        return $this->fetchCalculatedDowntimes(
            $this->selectActiveDowntimes()->where('dc.ts_expected_end > ?', $this->currentTime)
        );
    }

    /**
     * Active (calculated) Downtimes, which reached their expected end time
     *
     * @return DowntimeCalculated[]
     */
    protected function fetchFinished(): array
    {
        $this->logger->debug('Fetch finished Downtimes');
        return $this->fetchCalculatedDowntimes(
            $this->selectActiveDowntimes()->where('dc.ts_expected_end <= ?', $this->currentTime)
        );
    }

    /**
     * Calculated Downtimes, which should no longer be in effect, as there is no related config
     *
     * @return DowntimeCalculated[]
     */
    protected function fetchLost(): array
    {
        $this->logger->debug('Fetching lost calculated downtimes');
        $query = $this->db->select()
            ->from(['dc' => 'downtime_calculated'], [])
            ->joinLeft(['dr' => 'downtime_rule'], 'dr.next_calculated_uuid = dc.uuid', '*')
            ->where('dr.next_calculated_uuid IS NULL')
            ->where('dc.is_active = ?', 'y');

        return $this->fetchCalculatedDowntimes($query);
    }

    /**
     * @return DowntimeCalculated[]
     */
    protected function fetchCalculatedDowntimes(Select $select): array
    {
        $calculated = [];
        echo "$select\n";
        foreach ($this->db->fetchAll($select) as $row) {
            $calculated[] = DowntimeCalculated::fromSerialization($row);
        }

        return $calculated;
    }

    protected function selectActiveDowntimes(): Select
    {
        return $this->selectDowntimes()->where('dc.is_active = ?', 'y');
    }

    protected function selectDowntimes(): Select
    {
        return $this->db->select()
            ->from(['dr' => 'downtime_rule'], [])
            ->join(['dc' => 'downtime_calculated'], 'dr.next_calculated_uuid = dc.uuid', '*');
    }
}
