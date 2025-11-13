<?php

namespace Icinga\Module\Eventtracker\Reporting;

use DateTimeInterface as DT;
use gipfl\ZfDb\Adapter\Pdo\PdoAdapter;
use gipfl\ZfDb\Select;
use Icinga\Module\Eventtracker\Time;

class TopTalkers
{
    protected PdoAdapter $db;

    protected const AGGREGATIONS = [
        AggregationSubject::HOST => 'host_name',
        AggregationSubject::OBJECT => 'object_name',
        AggregationSubject::OBJECT_CLASS => 'object_class',
        AggregationSubject::PROBLEM_IDENTIFIER => 'problem_identifier',
    ];

    protected const COLUMNS = [
        'cnt_total' => 'COUNT(*)',
    ];
    protected string $aggregation;
    protected DT $start;
    protected DT $end;

    /**
     * @param string $aggregation Should become AggregationSubject
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
            $key = $row['aggregation'];
            unset($row['aggregation']);
            $result[$key] = array_map('intval', $row);
        }

        return $result;
    }

    public function select(): Select
    {
        $subject = $this->getAggregationExpression();
        return $this->db->select()->from('issue_history', ['aggregation' => $subject] + self::COLUMNS)
            ->where('ts_first_event >= ?', Time::dateTimeToTimestampMs($this->start))
            ->where('ts_first_event < ?', Time::dateTimeToTimestampMs($this->end))
            ->order('cnt_total DESC')
            ->group($subject)
            ->limit(50)
        ;
    }
}
