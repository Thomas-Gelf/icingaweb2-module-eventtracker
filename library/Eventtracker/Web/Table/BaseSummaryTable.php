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

    protected function getMainColumnAlias(): string
    {
        return \preg_replace('/^.+\./', '', $this->getMainColumn());
    }

    protected function getMainColumnTitle(): string
    {
        return $this->translate('Owner');
    }

    protected function initialize()
    {
        $column = $this->getMainColumnAlias();
        $this->enableMultiSelect(
            'eventtracker/issues',
            'eventtracker/issues',
            [$column]
        );

        $this->addAvailableColumns([
            $this->createColumn($this->getMainColumnAlias(), $this->getMainColumnTitle(), $this->getMainColumn())
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
        $column = $this->getMainColumnAlias();
        $label = self::zeroSplitLongStrings($this->noLabelIfNone($label));

        return Link::create($label, 'eventtracker/issues', [
            $column => $row->$column
        ]);
    }

    public function prepareQuery()
    {
        $column = $this->getMainColumn();
        $order = $this->getMainColumnAlias();

        $query = $this->db()
            ->select()
            ->from(['i' => 'issue'], $this->getRequiredDbColumns())
            ->order('COUNT(*) DESC')
            ->order("$order ASC")
            ->group($column);

        EventSummaryBySeverity::addAggregationColumnsToQuery($query);

        return $query;
    }

    protected function noLabelIfNone(?string $label): string
    {
        if ($label === null || \strlen($label) === 0) {
            return $this->translate('- none -');
        }

        return $label;
    }

    protected static function zeroSplitLongStrings($label): string
    {
        $zeroSpace = \html_entity_decode('&#8203;');
        $label = \wordwrap($label, 60, $zeroSpace, true);
        $parts = \preg_split('/\./', $label);
        foreach ($parts as & $part) {
            if (\strlen($part) > 64) {
                $part = \wordwrap($part, 32, $zeroSpace, true);
            } elseif (\strlen($part) > 10) {
                $part .= $zeroSpace;
            }
        }

        return \implode('.', $parts);
    }
}
