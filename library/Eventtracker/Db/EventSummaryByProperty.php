<?php

namespace Icinga\Module\Eventtracker\Db;

use gipfl\ZfDb\Adapter\Adapter as Db;
use gipfl\ZfDb\Select;

class EventSummaryByProperty
{
    const PROPERTY = 'UNDEFINED_PROPERTY';

    const CLASS_NAME = 'UNDEFINED_CLASS_NAME';

    protected $select;

    protected $originalSelect;

    public function __construct(Select $select)
    {
        $this->select = clone $select;
        $this->originalSelect = $select;
    }

    public function fetch(Db $db)
    {
        return $db->fetchRow($this->prepareQuery());
    }

    public static function addAggregationColumnsToQuery(Select $query)
    {
        $property = static::PROPERTY;
        $class = static::CLASS_NAME;
        foreach ($class::ENUM as $value) {
            $query->columns([
                "cnt_$value" => "COALESCE(SUM(CASE WHEN $property = '$value' THEN 1 ELSE 0 END), 0)",
                "cnt_${value}_handled"   => "COALESCE(SUM(CASE WHEN $property = '$value'"
                    . " AND status != 'open' THEN 1 ELSE 0 END), 0)",
                "cnt_${value}_unhandled" => "COALESCE(SUM(CASE WHEN $property = '$value'"
                    . " AND status = 'open' THEN 1 ELSE 0 END), 0)",
            ]);
        }
    }

    protected function prepareQuery()
    {
        $query = clone $this->select;
        $query->reset(Select::COLUMNS)
            ->reset(Select::ORDER)
            ->reset(Select::LIMIT_COUNT)
            ->reset(Select::LIMIT_OFFSET);

        static::addAggregationColumnsToQuery($query);

        return $query;
    }
}
