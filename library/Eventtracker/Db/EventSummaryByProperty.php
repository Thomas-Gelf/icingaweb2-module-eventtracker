<?php

namespace Icinga\Module\Eventtracker\Db;

use gipfl\IcingaWeb2\Url;
use Zend_Db_Adapter_Pdo_Abstract as DbAdapter;
use Zend_Db_Select as DbSelect;

class EventSummaryByProperty
{
    const PROPERTY = 'UNDEFINED_PROPERTY';

    const CLASS_NAME = 'UNDEFINED_CLASS_NAME';

    protected $select;

    protected $originalSelect;

    public function __construct(DbSelect $select)
    {
        $this->select = clone $select;
        $this->originalSelect = $select;
    }

    public function fetch(DbAdapter $db)
    {
        return $db->fetchRow($this->prepareQuery());
    }

    public static function addAggregationColumnsToQuery(DbSelect $query)
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
        $query->reset(DbSelect::COLUMNS)
            ->reset(DbSelect::ORDER)
            ->reset(DbSelect::LIMIT_COUNT)
            ->reset(DbSelect::LIMIT_OFFSET);

        static::addAggregationColumnsToQuery($query);

        return $query;
    }
}
