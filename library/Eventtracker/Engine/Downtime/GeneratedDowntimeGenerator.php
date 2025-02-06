<?php

namespace Icinga\Module\Eventtracker\Engine\Downtime;

use Cron\CronExpression;
use DateTimeInterface;
use Exception;
use gipfl\ZfDb\Select;
use Icinga\Module\Eventtracker\Daemon\DbBasedComponent;
use Icinga\Module\Eventtracker\Daemon\SimpleDbBasedComponent;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use React\EventLoop\Loop;

use function max;
use function min;

/**
 * Logik:
 *
 * - definiere $now
 * - generiere "downtime_calculated" bis "Horizont"
 * - setzt is_active = y für alle,
 * - hole alle, deren ts_expected_start <= $now und ts_expected_end >= $now ist
 * - lade die zugehörigen rules
 * - rule-> suche affected issues, mit hostlist und ggf filter
 *
 */
class GeneratedDowntimeGenerator implements DbBasedComponent
{
    use SimpleDbBasedComponent;

    private const TABLE = 'downtime_calculated';

    /** @var DateTimeInterface */
    protected $firstPossibleTimestamp;

    /** @var DateTimeInterface */
    protected $lastPossibleTimestamp;

    /** @var LoggerInterface */
    protected $logger;

    /** @var DowntimeRule[] */
    protected $lastRules = [];

    /**
     * Nothing happens here. We're a DbBasedComponent, which means that we cannot
     * rely on having a $db.
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Triggers calculation for the given rules
     *
     * This is currently being called by the "RunningConfig" implementation, watching rules
     * in the DB. However, that implementation is currently only being triggered once it got
     * a DB connection
     *
     * @param DowntimeRule[] $rules
     * @return void
     */
    public function triggerCalculation(array $rules)
    {
        if ($this->db === null) {
            $this->logger->notice(__CLASS__ . ': Ignoring rules, have no DB' . var_export($this->db, 1));
            $this->lastRules = $rules;
            $this->scheduleNextCalculation(3);
            return;
        }

        $now = time() * 1000;
        $horizon = (time() + 86400 * 90) * 1000;
        /*$this->logger->debug(sprintf(
            "Calculating from %s to %s",
            date('Y-m-d', $now / 1000),
            date('Y-m-d', $horizon / 1000)
        ));*/
        // For recurring Downtimes, start calculating after this timestamp
        $this->firstPossibleTimestamp = $this->tsToDateTime($now);
        // For recurring Downtimes, schedule no instance after this timestamp
        $this->lastPossibleTimestamp = $this->tsToDateTime($horizon);
        $rulesDisabled = [];
        foreach ($rules as $rule) {
            if (!$rule->isEnabled()) {
                $calculatedUuid = $rule->get('rule_config_uuid');
                if ($calculatedUuid !== null) {
                    $rulesDisabled[] = $calculatedUuid;
                }
            }
        }

        if (! empty($rulesDisabled)) {
            // TODO: doing this on start and on "lost" rules only would be more efficient
            $this->db->delete(self::TABLE, $this->db->quoteInto('rule_config_uuid IN (?)', $rulesDisabled));
        }


        $affectedOutdated = $this->wipeOutdatedCalculations();
        $affectedOrphaned = $this->deleteOrphanedCalculations();
        // TODO: foreach outdated, orphaned: loop over affected issues, back to problem unless they are affected
        // by other downtimes. Figure out whether they should better be closed?
        foreach ($rules as $rule) {
            if ($rule->isEnabled()) {
                // echo JsonString::encode($rule, JSON_PRETTY_PRINT);
                $this->generateForRule($rule);
            }
        }

        $this->lastRules = $rules;
        $this->scheduleNextCalculation(60);
    }

    private function scheduleNextCalculation(int $timeout)
    {
        Loop::get()->addTimer($timeout, function () {
            $this->triggerCalculation($this->lastRules);
        });
    }

    /**
     * Removes outdated calculated downtimes, returns affected issue UUIDs
     *
     * Outdated are calculated downtimes with an expected end in the past
     *
     * @return UuidInterface[]
     */
    public function wipeOutdatedCalculations(): array
    {
        $timestamp = $this->firstPossibleTimestamp->getTimestamp() * 1000;
        $where = 'ts_expected_end < ?';

        $uuids = $this->fetchUuids($this->selectAffectedIssues()->where($where, $timestamp));
        $this->db->delete(self::TABLE, $this->db->quoteInto($where, $timestamp));

        return $uuids;
    }

    /**
     * Removes calculated downtimes for former rule configurations, returns affected issue UUIDs
     *
     * Orphaned are Downtime Calculations related to former Downtime Rule configurations
     *
     * @return UuidInterface[]
     */
    public function deleteOrphanedCalculations(): array
    {
        $where = 'rule_config_uuid IS NULL';
        $this->db->delete(self::TABLE, $where);

        return $this->fetchUuids($this->selectAffectedIssues()->where($where));
    }

    protected function selectAffectedIssues(): Select
    {
        return $this->selectCalculatedDowntimes()
            ->join(['dai' => 'downtime_affected_issue'], 'dc.uuid = dai.calculation_uuid', 'issue_uuid');
    }

    /**
     * @param $query
     * @return UuidInterface[]
     */
    protected function fetchUuids($query): array
    {
        $uuids = [];

        foreach ($this->db->fetchCol($query) as $uuid) {
            $uuids[] = Uuid::fromBytes($uuid);
        }

        return $uuids;
    }

    protected function cleanupForRule(DowntimeRule $rule)
    {
    }

    /**
     * Decides which calculation strategy to pick for the given rule
     */
    protected function generateForRule(DowntimeRule $rule): void
    {
        $label = $rule->get('label');
        try {
            if ($definition = $rule->get('time_definition')) {
                if (substr($definition, 0, 1) === '@') { // simple recurrence
                    $this->logger->notice("Skipping simple recurrence: $label");
                } else {
                    $this->generateForRuleWithTimeDefinition($rule, $definition);
                }
            } else {
                $this->generateForRuleWithoutTimeDefinition($rule);
            }
        } catch (Exception $e) {
            $this->logger->error("Downtime generator failed for $label: " . $e->getMessage());
        }
    }

    protected function generateForRuleWithoutTimeDefinition(DowntimeRule $rule): void
    {
        $start = $this->getTsStart($rule);
        $end = $this->getTsEnd($rule);
        $cnt = $this->countCalculatedDowntimes($rule);
        $db = $this->dbStore->getDb();

        if ($cnt === 0) {
            $db->beginTransaction();
            try {
                // $this->logger->debug('Calculating downtime for ' . $rule->get('label'));
                $this->dbStore->store(DowntimeCalculated::createCalculated($rule, $start));
                $this->refreshNextCalculatedUuidReference($rule);
                $db->commit();
            } catch (\Throwable $e) {
                $this->logger->error('Generating for rule w/o definition failed: ' . $e->getMessage());
                try {
                    $db->rollBack();
                } catch (Exception $e) {
                }
            }
        }
        // $this->logger->notice("Skipping empty definition: $label");
    }

    /**
     * @throws Exception
     */
    protected function generateForRuleWithTimeDefinition(DowntimeRule $rule, string $definition)
    {
        $start = $this->getTsStart($rule);
        $end = $this->getTsEnd($rule);

        $maxAmount = 100;
        $cron = new CronExpression($definition);
        $former = $cron->getPreviousRunDate($start, 0, $rule->get('timezone'));
        $formerDowntime = DowntimeCalculated::createCalculated($rule, $former);
        if ($formerDowntime->getExpectedStart() < $this->fetchTsLastCalculation($rule)) {
            if ($formerDowntime->getExpectedEnd() > $start) {
                $this->dbStore->store($formerDowntime);
            }
        }
        $db = $this->dbStore->getDb();
        $started = false;
        try {
            if ($start < $end) {
                $db->beginTransaction();
                $started = true;
            }
            $cnt = $this->countCalculatedDowntimes($rule);
            while ($start < $end && $cnt < $maxAmount) {
                $next = $cron->getNextRunDate($start, 0, false, $rule->get('timezone'));
                if ($next <= $end) {
                    $start = $next;
                    $this->dbStore->store(DowntimeCalculated::createCalculated($rule, $next));
                } else {
                    break;
                }
                $cnt++;
            }
            if ($started) {
                $this->refreshNextCalculatedUuidReference($rule);
                $db->commit();
            }
        } catch (Exception $e) {
            $this->logger->error('Generating rule with definition failed: ' . $e->getMessage());
            if ($started) {
                try {
                    $db->rollBack();
                } catch (Exception $e) {
                }
            }
        }
    }

    protected function refreshNextCalculatedUuidReference(DowntimeRule $rule)
    {
        $db = $this->dbStore->getDb();
        $nextUuid = $this->getNextCalculatedBinaryUuid($rule);
        $logLabel = $rule->get('label');
        if ($nextUuid === $rule->get('next_calculated_uuid')) {
            /*$this->logger->debug(sprintf(
                'Keeping next calculated UUID for %s: %s',
                $logLabel,
                Uuid::fromBytes($nextUuid)->toString()
            ));*/
        } else {
            $this->logger->notice(sprintf(
                'Advancing next calculated UUID for %s: %s',
                $logLabel,
                Uuid::fromBytes($nextUuid)->toString()
            ));
        }

        $db->update($rule->getTableName(), [
            'next_calculated_uuid'   => $nextUuid,
        ], $db->quoteInto('config_uuid = ?', $rule->get('config_uuid')));
        $rule->set('next_calculated_uuid', $nextUuid);
        // $this->store->store($rule); --> ?? does this work right now?
    }

    protected function countCalculatedDowntimes(DowntimeRule $rule): int
    {
        $db = $this->dbStore->getDb();
        return (int) $db->fetchOne(
            $this->selectCalculatedDowntimes('COUNT(*)')
                ->where('dc.rule_config_uuid = ?', $rule->get('config_uuid'))
        );
    }

    protected function getNextCalculatedBinaryUuid(DowntimeRule $rule): ?string
    {
        $db = $this->dbStore->getDb();
        $nextUuid = $db->fetchOne(
            $this->selectCalculatedDowntimes('uuid')
            ->where('dc.rule_config_uuid = ?', $rule->get('config_uuid'))
            ->order('ts_expected_start')
            ->limit(1)
        );
        if (! $nextUuid) {
            return null;
        }

        return $nextUuid;
    }

    protected function dateTimeToTs(DateTimeInterface $dateTime): int
    {
        return (int) $dateTime->getTimestamp() * 1000;
    }

    protected function tsToDateTime(int $timestampMs): DateTimeInterface
    {
        return new \DateTime('@' . floor($timestampMs / 1000));
    }

    protected function getTsStart(DowntimeRule $rule): DateTimeInterface
    {
        $logLabel = $rule->get('label');
        if ($tsLastCalculation = $this->fetchTsLastCalculation($rule)) {
            $start = max($this->firstPossibleTimestamp, $tsLastCalculation);
            /*$this->logger->debug(sprintf(
                'Get TS start for %s: last calc %s, first possible = %s, max is %s',
                $logLabel,
                $this->dateTimeToTs($tsLastCalculation),
                $this->dateTimeToTs($this->firstPossibleTimestamp),
                $this->dateTimeToTs($start)
            ));*/
        } else {
            $start = $this->firstPossibleTimestamp;
            /*$this->logger->debug(sprintf(
                'TS start for %s is first possible: %s',
                $logLabel,
                $this->dateTimeToTs($start)
            ));*/
        }

        if ($notBefore = $rule->get('ts_not_before')) {
            $notBefore = $this->tsToDateTime($notBefore);
            $start = max($start, $notBefore);
            /*$this->logger->debug(sprintf(
                'TS start for %s, not before kicked in: %s',
                $logLabel,
                $this->dateTimeToTs($start)
            ));*/
        }

        return $start;
    }

    protected function getTsEnd(DowntimeRule $rule): DateTimeInterface
    {
        $end = $this->lastPossibleTimestamp;
        if ($notAfter = $rule->get('ts_not_after')) {
            $notAfter = $this->tsToDateTime($notAfter);
            $end = min($end, $notAfter);
        }

        return $end;
    }

    protected function fetchTsLastCalculation(DowntimeRule $rule): ?DateTimeInterface
    {
        $db = $this->dbStore->getDb();
        $value = $db->fetchOne($this->selectCalculatedDowntimes([
            'ts' => 'MAX(dc.ts_expected_start)'
        ])->where('dc.rule_config_uuid = ?', $rule->get('config_uuid')));

        if ($value) {
            // $this->logger->debug("Fetched TS last calculation: " . ((int) $value));
            return $this->tsToDateTime((int) $value);
        }

        return null;
    }

    protected function selectCalculatedDowntimes($columns = []): Select
    {
        return $this->db->select()->from(['dc' => self::TABLE], $columns);
    }
}
