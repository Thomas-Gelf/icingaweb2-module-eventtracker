<?php

namespace Icinga\Module\Eventtracker\Web\Table;

use gipfl\Format\LocalDateFormat;
use gipfl\Translation\TranslationHelper;
use Icinga\Module\Eventtracker\Time;
use ipl\Html\HtmlElement;

class ScheduledDowntimesTable extends BaseTable
{
    use TranslationHelper;

    protected $searchColumns = [
    ];

    public function renderRow($row): HtmlElement
    {
        if (isset($row->ts_expected_start)) {
            $this->renderDayIfNew($this->formatTsHeader($row->ts_expected_start), count($this->getChosenColumns()));
        }
        return parent::renderRow($row);
    }

    /**
     * @param  int $timestamp
     */
    protected function renderDayIfNew($timestamp, $colspan = 2)
    {
        if ($this->lastDay !== $timestamp) {
            $this->nextHeader()->add(
                $this::th($timestamp, [
                    'colspan' => $colspan,
                    'class'   => 'table-header-day'
                ])
            );

            $this->lastDay = $timestamp;
            $this->nextBody();
        }
    }

    protected function formatTsHeader($ts)
    {
        $timestamp = (int) ($ts / 1000);
        $now = time();
        $formatter = new LocalDateFormat();
        return $formatter->getFullDay($timestamp);
        if ($timestamp > ($now - 120)) {
            return $this->translate('Now');
        }
        if ($timestamp > ($now - 600)) {
            return sprintf($this->translate('%d minutes ago'), floor(($now - $timestamp) / 60));
        }

        return $this->getDateFormatter()->getFullDay($timestamp);
    }

    protected function renderTitleColumns()
    {
        return null;
    }

    protected function initialize()
    {
        $this->addAvailableColumns([
            $this->createColumn('ts_expected_start', $this->translate('Downtime Rule'), [
                'ts_expected_start' => 'dc.ts_expected_start',
                'ts_expected_end'   => 'dc.ts_expected_end',
            ])->setRenderer(function ($row) {
                return date('H:i', (int) ($row->ts_expected_start / 1000))
                    . '-'
                    . date('H:i', (int) ($row->ts_expected_end / 1000))
                    . ': '
                    . $row->label;
                return Time::agoFormatted($row->received);
            }),
            $this->createColumn('label', $this->translate('Downtime Rule'), [
                'label'             => 'dr.label',
            ])->setRenderer(function ($row) {
                return ;
                return Time::agoFormatted($row->received);
            }),
        ]);
    }

    public function getDefaultColumnNames()
    {
        return [
            'label',
            'ts_expected_start',
        ];
    }

    public function prepareQuery()
    {
        return $this->db()->select()
            ->from(['dc' => 'downtime_calculated'], $this->getRequiredDbColumns())
            ->join(['dr' => 'downtime_rule'], 'dr.config_uuid = dc.rule_config_uuid', [])
            ->order('dc.ts_expected_start ASC');
    }
}
