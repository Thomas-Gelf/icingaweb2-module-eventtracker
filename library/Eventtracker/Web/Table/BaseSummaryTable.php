<?php

namespace Icinga\Module\Eventtracker\Web\Table;

use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Table\Extension\MultiSelect;
use gipfl\IcingaWeb2\Url;
use gipfl\Translation\TranslationHelper;
use Icinga\Module\Eventtracker\Db\EventSummaryBySeverity;
use Icinga\Module\Eventtracker\Web\Widget\SeverityFilter;

abstract class BaseSummaryTable extends BaseTable
{
    use TranslationHelper;
    use MultiSelect;

    protected $defaultAttributes = [
        'class' => ['common-table', 'table-row-selectable'],
        'data-base-target' => 'col1',
    ];

    abstract protected function getMainColumn();

    protected function getMainColumnTitle()
    {
        return $this->translate('Owner');
    }

    protected function initialize()
    {
        $column = $this->getMainColumn();
        $this->enableMultiSelect(
            'eventtracker/issues',
            'eventtracker/issues',
            [$column]
        );
        $this->addAvailableColumns([
            $this->createColumn($column, $this->getMainColumnTitle())
                ->setRenderer(function ($row) use ($column) {
                    return $this->linkToClass($row, $row->$column);
                }),
            $this->createColumn('cnt', ' ', [
                'cnt' => 'COUNT(*)',
            ])->setRenderer(function ($row) {
                $url = Url::fromPath('eventtracker/issues');
                $summary = new SeverityFilter($row, $url);

                return $this::td($summary->skipMissing(), [
                    'align' => 'right'
                ]);
            }),
        ]);
    }

    protected function linkToClass($row, $label)
    {
        $column = $this->getMainColumn();
        if (\strlen($label) === 0) {
            $label = $this->translate('- none -');
        }
        return Link::create($label, 'eventtracker/issues', [
            $column => $row->$column
        ]);
    }

    public function prepareQuery()
    {
        $column = $this->getMainColumn();

        $query = $this->db()
            ->select()
            ->from('issue', $this->getRequiredDbColumns())
            ->order('COUNT(*) DESC')
            ->order("$column ASC")
            ->group($column);

        EventSummaryBySeverity::addAggregationColumnsToQuery($query);

        return $query;
    }
}
