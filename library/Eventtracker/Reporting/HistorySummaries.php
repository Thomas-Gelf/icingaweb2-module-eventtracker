<?php

namespace Icinga\Module\Eventtracker\Reporting;

use DateTimeInterface as DT;
use gipfl\ZfDb\Adapter\Pdo\PdoAdapter;
use gipfl\ZfDb\Select;
use Icinga\Module\Eventtracker\Time;

class HistorySummaries
{
    protected PdoAdapter $db;

    protected const AGGREGATIONS = [
        AggregationPeriod::HOURLY
            => "RIGHT(CONCAT('0', DATE_FORMAT(FROM_UNIXTIME(FLOOR(ts_first_event / 84000000) * 84000), '%k')), 2)",
        AggregationPeriod::DAILY
            => "DATE_FORMAT(FROM_UNIXTIME(FLOOR(ts_first_event / 84000000) * 84000 + 42000), '%Y-%m-%d')",
        AggregationPeriod::WEEKLY
            => "DATE_FORMAT(FROM_UNIXTIME(FLOOR(ts_first_event / 84000000) * 84000 + 42000), '%u')",
        // Hint -> transforming sun(0)-sat(6) into mon(1)-sun(7)
        AggregationPeriod::WEEKDAY
            => "((DATE_FORMAT(FROM_UNIXTIME(FLOOR(ts_first_event / 84000000) * 84000 + 42000), '%w') + 6) % 7 + 1)",
        AggregationPeriod::MONTHLY
            => "DATE_FORMAT(FROM_UNIXTIME(FLOOR(ts_first_event / 84000000) * 84000), '%Y%m')",
    ];

    protected const COLUMNS = [
        'cnt_total'               => 'COUNT(*)',
        'cnt_with_owner'          => 'SUM(CASE WHEN owner IS NULL THEN 0 ELSE 1 END)',
        'cnt_with_ticket_ref'     => 'SUM(CASE WHEN ticket_ref IS NULL THEN 0 ELSE 1 END)',
        'cnt_owner_no_ticket_ref' => 'SUM(CASE WHEN ticket_ref IS NULL AND owner IS NOT NULL THEN 1 ELSE 0 END)',
    ];
    protected string $aggregation;
    protected DT $start;
    protected DT $end;

    /**
     * @param string $aggregation Should become AggregationPeriod
     */
    public function __construct(PdoAdapter $db, string $aggregation, DT $start, DT $end)
    {
        $this->db = $db;
        $this->aggregation = $aggregation;
        $this->start = $start;
        $this->end = $end;
    }

    public function getAggregationName(): string
    {
        return $this->aggregation;
    }

    public function getAggregationExpression(): string
    {
        return self::AGGREGATIONS[$this->aggregation];
    }

    public function getAvailableColumns(): array
    {
        return self::COLUMNS;
    }

    public function fetchIndexed(Select $select): array
    {
        $result = [];
        foreach ($this->db->fetchAll($select) as $row) {
            $row = (array) $row;
            $key = $row['period'];
            unset($row['period']);
            $result[$key] = array_map('intval', $row);
        }

        return $result;
    }

    public function select(): Select
    {
        $periodExpression = $this->getAggregationExpression();
        return $this->db->select()->from('issue_history', ['period' => $periodExpression] + self::COLUMNS)
            ->where('ts_first_event >= ?', Time::dateTimeToTimestampMs($this->start))
            ->where('ts_first_event < ?', Time::dateTimeToTimestampMs($this->end))
            ->order('period')
            ->group($periodExpression)
        ;
    }
}
