<?php

namespace Icinga\Module\Eventtracker\Web\Table\Reporting;

use gipfl\ZfDb\Adapter\Pdo\PdoAdapter;
use gipfl\ZfDb\Select;
use Icinga\Module\Eventtracker\Reporting\TopTalkers;
use Icinga\Module\Eventtracker\Web\Table\BaseTable;

class TopTalkersTable extends BaseTable
{
    protected TopTalkers $report;

    public function __construct(PdoAdapter $db, TopTalkers $report)
    {
        parent::__construct($db);
        $this->report = $report;
    }

    public function initialize()
    {
        $expressions = $this->report->getAvailableColumns();
        $this->addAvailableColumns([
            $this->createColumn('aggregation', $this->translate('Subject'), [
                'aggregation' => $this->report->getAggregationExpression(),
            ]),
            $this->createColumn('cnt_total', $this->translate('Issues'), [
                'cnt_total' => $expressions['cnt_total'],
            ]),
        ]);
    }

    protected function prepareQuery(): Select
    {
        return $this->report->select();
    }
}
