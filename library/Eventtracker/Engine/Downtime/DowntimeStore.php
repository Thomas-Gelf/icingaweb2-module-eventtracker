<?php

namespace Icinga\Module\Eventtracker\Engine\Downtime;

use Exception;
use gipfl\ZfDb\Adapter\Adapter;
use Icinga\Module\Eventtracker\Issue;
use Icinga\Module\Eventtracker\IssueHistory;
use Icinga\Module\Eventtracker\Time;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class DowntimeStore
{
    const RECENT_ISSUE_CHECK_SECONDS = 86400;
    protected static ?int $lastNow = 0;

    protected Adapter $db;
    protected LoggerInterface $logger;
    /** @var HostList[] */
    protected array $hostLists = [];

    public function __construct(Adapter $db, LoggerInterface $logger)
    {
        $this->db = $db;
        $this->logger = $logger;
    }

    /**
     * Fetch open issues, that have been created recently
     *
     * @return Issue[]
     */
    public function fetchRecentOpenIssues(): array
    {
        $db = $this->db;
        return self::dbResultsToIssues($db->fetchAll(
            $db->select()->from('issue')
                ->where('status = ?', 'open')
                ->where('ts_first_event > ?', (time() - self::RECENT_ISSUE_CHECK_SECONDS) * 1000)
        ));
    }

    /**
     * Fetch open issues, that have been created recently
     *
     * @return Issue[]
     */
    public function fetchAllIssuesInDowntime(): array
    {
        $db = $this->db;
        return self::dbResultsToIssues($db->fetchAll(
            $db->select()->from(['i' => 'issue'], 'i.*')
                ->join(['r' => 'downtime_rule'], 'i.downtime_config_uuid = r.config_uuid', [])
                ->where('i.status = ?', 'in_downtime')
        ));
    }

    /**
     * Fetch open issues, that have been created recently
     *
     * @return Issue[]
     */
    public function fetchIssuesInDowntime(UuidInterface $ruleUuid): array
    {
        $db = $this->db;
        return self::dbResultsToIssues($db->fetchAll(
            $db->select()->from(['i' => 'issue'])
                ->where('status = ?', 'in_downtime')
                ->where('i.downtime_rule_uuid = ?', $ruleUuid->getBytes())
        ));
    }

    /**
     * @return Issue[]
     */
    protected function loadIssuesAffectedByRuleConfig(string $configUuid): array
    {
        $query = $this->db->select()
            ->from(['i' => 'issue'], '*')
            ->where('i.downtime_rule_uuid = ?', $configUuid);

        return self::dbResultsToIssues($this->db->fetchAll($query));
    }

    public function setDowntimeForIssue(Issue $issue, DowntimeRule $rule)
    {
        $this->runTransaction(fn () => $this->runSetDowntimeForIssue($issue, $rule), 'set a downtime');
    }

    public function logDowntimeActivation(Issue $issue, DowntimeRule $rule, ?int $now = null)
    {
        $this->db->insert('issue_downtime_history', [
            'ts_modification'  => self::safeNow($now),
            'issue_uuid'       => $issue->get('issue_uuid'),
            'rule_uuid'        => $rule->get('uuid'),
            'rule_config_uuid' => $rule->get('config_uuid'),
            'action'           => 'activated',
        ]);
    }

    public function logDowntimeDeactivation(Issue $issue, ?DowntimeRule $rule, ?int $now = null)
    {
        $this->db->insert('issue_downtime_history', [
            'ts_modification'  => self::safeNow($now),
            'issue_uuid'       => $issue->get('issue_uuid'),
            'rule_uuid'        => $rule ? $rule->get('uuid') : null,
            'rule_config_uuid' => $rule ? $rule->get('config_uuid') : null,
            'action'           => 'deactivated',
        ]);
    }

    protected function runSetDowntimeForIssue(Issue $issue, DowntimeRule $rule)
    {
        $now = Time::unixMilli();
        $wasInDowntime = $issue->get('status') === 'in_downtime';
        $issue->set('status', 'in_downtime');
        $issue->set('downtime_rule_uuid', $rule->get('uuid'));
        $issue->set('downtime_config_uuid', $rule->get('config_uuid'));
        $issue->set('ts_downtime_expired', null);
        if (!$wasInDowntime || $issue->get('downtime_rule_uuid') !== $rule->get('uuid')) {
            $issue->set('ts_downtime_triggered', $now);
            $this->logDowntimeActivation($issue, $rule, $now);
        }
        $issue->storeToDb($this->db);
    }

    public function removeDowntimeForIssue(Issue $issue, ?DowntimeRule $rule): void
    {
        if ($issue->get('status') !== 'in_downtime') {
            return;
        }
        $this->runTransaction(fn () => $this->runRemoveDowntimeForIssue($issue, $rule), 'remove a downtime');
    }

    protected function runRemoveDowntimeForIssue(Issue $issue, ?DowntimeRule $rule): void
    {
        $now = Time::unixMilli();
        $issue->set('ts_downtime_expired', $now);
        // This is related to issues with limited duration. However, removing triggered will re-trigger
        // it within the very same slot once again. Leaving this here for reference
        // $issue->set('ts_downtime_triggered', null);
        if ($rule) {
            $status = $rule->get('on_iteration_end_issue_status');
            if ($status === DowntimeRule::END_STATUS_CLOSED) {
                Issue::closeIssue($issue, $this->db, IssueHistory::REASON_EXPIRATION, 'downtime');
                $this->logDowntimeDeactivation($issue, $rule, $now);
                return; // We do not want to reach storeToDb
            } else {
                $issue->set('status', $rule->get('on_iteration_end_issue_status'));
            }
        } else {
            $issue->set('status', 'open');
        }

        // Hint: we leave downtime_config_uuid and ts_downtime_triggered to not trigger the
        // same downtime twice, depending on rule configuration
        $issue->storeToDb($this->db);
        $this->logDowntimeDeactivation($issue, $rule, $now);
    }

    public function getHostList(string $binaryUuid): ?HostList
    {
        if (!isset($this->hostLists[$binaryUuid])) {
            if ($list = HostList::load(Uuid::fromBytes($binaryUuid), $this->db)) {
                $this->hostLists[$binaryUuid] = $list;
            }
        }

        return $this->hostLists[$binaryUuid] ?? null;
    }

    public function forgetHostLists(): void
    {
        $this->logger->notice('Forgot all Host lists');
        $this->hostLists = [];
    }

    protected function runTransaction(callable $callback, string $taskDescription)
    {
        $db = $this->db;
        $db->beginTransaction();
        try {
            $callback();
            $db->commit();
        } catch (Exception $e) {
            $this->logger->error(sprintf('Could not %s: %s', $taskDescription, $e->getMessage()));
            try {
                $db->rollBack();
            } catch (Exception $e) {
                // ignore.
            }

            throw $e;
        }
    }

    /**
     * @param array $rows
     * @return Issue[]
     */
    protected static function dbResultsToIssues(array $rows): array
    {
        $issues = [];
        foreach ($rows as $row) {
            $issue = Issue::fromSerialization($row);
            $issue->setStored();
            $issues[$issue->get('issue_uuid')] = $issue;
        }

        return $issues;
    }

    protected static function safeNow(?int $now): int
    {
        if ($now === null) {
            $now = Time::unixMilli();
        }
        if ($now < self::$lastNow && self::$lastNow - $now < 300_000) {
            $now = self::$lastNow + 1;
        } elseif ($now === self::$lastNow) {
            $now += 1;
        }

        return $now;
    }
}
