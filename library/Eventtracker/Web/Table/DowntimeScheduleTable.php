<?php

namespace Icinga\Module\Eventtracker\Web\Table;

use gipfl\IcingaWeb2\Table\QueryBasedTable;
use gipfl\Translation\TranslationHelper;
use Icinga\Module\Eventtracker\Db\SelectPaginationAdapter;
use Icinga\Module\Eventtracker\Engine\Downtime\DowntimeRule;
use Icinga\Data\DataArray\ArrayDatasource;

class DowntimeScheduleTable extends QueryBasedTable
{
    use TranslationHelper;

    protected DowntimeRule $rule;
    protected ?string $currentDateString = null;
    protected ?SelectPaginationAdapter $paginationAdapter = null;

    public function __construct(DowntimeRule $rule)
    {
        $this->rule = $rule;
    }

    public function getQuery()
    {
        return $this->query ??= $this->prepareQuery();
    }

    public function prepareQuery()
    {
        $ds = new ArrayDatasource([]);
        return $ds->select();
    }

    protected function getPaginationAdapter()
    {
        return $this->paginationAdapter ??=new SelectPaginationAdapter($this->getQuery());
    }

    protected function fetchQueryRows()
    {
        return $this->getQuery()->fetchAll();
    }
}
