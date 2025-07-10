<?php

namespace Icinga\Module\Eventtracker\Db;

use gipfl\IcingaWeb2\Zf1\Db\FilterRenderer;
use gipfl\ZfDb\Adapter\Adapter as Db;
use gipfl\ZfDb\Select;
use Icinga\Data\Filter\Filter;
use Icinga\Data\Filter\FilterAnd;
use Icinga\Data\Filter\FilterLessThan;
use Icinga\Data\Filter\FilterMatch;
use Icinga\Data\Filter\FilterOr;

class DbCleanup
{
    protected Db $db;
    protected DbCleanupFilter $filter;
    protected string $table;

    public function __construct(Db $db, string $table, DbCleanupFilter $filter)
    {
        $this->db = $db;
        $this->table = $table;
        $this->filter = $filter;
    }

    public function delete(): int
    {
        // $query = $this->db->delete()
        $query = (string) $this->prepareSelectQuery();
        $expectedPrefix = sprintf(' FROM %s WHERE ', $this->table);
        $prefixLength = strlen($expectedPrefix);
        if (substr($query, 0, $prefixLength) !== $expectedPrefix) {
            throw new \RuntimeException("Query is expected to start with $expectedPrefix, got $query");
        }

        return $this->db->delete($this->table, substr($query, $prefixLength));
    }

    public function count(): int
    {
        return (int) $this->db->fetchOne($this->prepareSelectQuery()->columns('COUNT(*)'));
    }

    protected function prepareSelectQuery(): Select
    {
        $query = $this->db->select()->from($this->table, []);
        FilterRenderer::applyToQuery($this->prepareFilter(), $query);

        return $query;
    }

    protected function prepareFilter(): Filter
    {
        $filter = new FilterAnd();
        if ($this->filter->hasTimeConstraint()) {
            $filter->addFilter(new FilterLessThan('ts_last_modified', '<', $this->filter->getTimestampLimit()));
        }
        foreach ($this->filter->getColumnFilters() as $column => $values) {
            if (count($values) === 1) {
                $filter->addFilter(new FilterMatch($column, '=', $values[0]));
            } else {
                $subFilter = new FilterOr();
                foreach ($values as $value) {
                    $subFilter->addFilter(new FilterMatch($column, '=', $value));
                }

                $filter->addFilter($subFilter);
            }
        }

        return $filter;
    }
}
