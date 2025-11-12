<?php

namespace Icinga\Module\Eventtracker\Web\Table\Reporting;

use gipfl\ZfDb\Adapter\Pdo\PdoAdapter;
use gipfl\ZfDb\Select;
use Icinga\Module\Eventtracker\Reporting\AggregationPeriodTitle;
use Icinga\Module\Eventtracker\Reporting\HistorySummaries;
use Icinga\Module\Eventtracker\Web\Table\BaseTable;

class HistorySummaryTable extends BaseTable
{
    protected HistorySummaries $report;
    protected AggregationPeriodTitle $titleFormatter;

    public function __construct(PdoAdapter $db, HistorySummaries $report)
    {
        parent::__construct($db);
        $this->report = $report;
        $this->titleFormatter = new AggregationPeriodTitle($report->getAggregationName());
    }

    public function initialize()
    {
        $expressions = $this->report->getAvailableColumns();
        $this->addAvailableColumns([
            $this->createColumn('period', $this->translate('Period'), [
                'period' => $this->report->getAggregationExpression(),
            ])->setRenderer(fn ($row) => $this->titleFormatter->getTranslated($row->period)),
            $this->createColumn('cnt_total', $this->translate('Issues'), [
                'cnt_total' => $expressions['cnt_total'],
            ]),
            $this->createColumn('cnt_with_owner', $this->translate('With Owner'), [
                'cnt_total' => $expressions['cnt_with_owner'],
            ]),
            $this->createColumn('cnt_with_ticket_ref', $this->translate('With Ticket'), [
                'cnt_total' => $expressions['cnt_with_ticket_ref'],
            ]),
            $this->createColumn('cnt_owner_no_ticket_ref', $this->translate('Owner, but no ticket'), [
                'cnt_total' => $expressions['cnt_owner_no_ticket_ref'],
            ]),
        ]);
    }

    protected function prepareQuery(): Select
    {
        return $this->report->select();
    }
}
