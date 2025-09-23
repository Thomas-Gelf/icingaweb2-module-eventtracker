<?php

namespace Icinga\Module\Eventtracker\Web\Table;

use gipfl\Format\LocalDateFormat;
use gipfl\Format\LocalTimeFormat;
use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Url;
use gipfl\Translation\TranslationHelper;
use Icinga\Module\Eventtracker\Web\WebActions;
use Icinga\Module\Eventtracker\Web\Widget\ConfigHistoryDetails;
use ipl\Html\HtmlElement;

class ConfigurationHistoryTable extends BaseTable
{
    use TranslationHelper;

    protected $searchColumns = [
        'ch.action',
    ];
    protected WebActions $actions;

    public function __construct($db, ?Url $url = null)
    {
        $this->actions = new WebActions();
        parent::__construct($db, $url);
    }
    protected function renderTitleColumns()
    {
        return null;
    }

    public function renderRow($row): HtmlElement
    {
        if (isset($row->ts_modification)) {
            $this->renderDayIfNew($this->formatTsHeader($row->ts_modification), count($this->getChosenColumns()));
        }
        return parent::renderRow($row);
    }

    public function renderDayIfNew($timestamp, $colspan = 2)
    {
        if ($this->lastDay !== $timestamp) {
            $this->nextHeader()->add(
                $this::th($timestamp, [
                    'colspan' => $colspan,
                    'class' => 'table-header-day'
                ])
            );

            $this->lastDay = $timestamp;
            $this->nextBody();
        }
    }

    protected function initialize()
    {

        $this->addAvailableColumns([
            $this->createColumn('ts_modification', $this->translate('Time'), [
                'ts_modification' => 'ch.ts_modification',
                'object_uuid' => 'ch.object_uuid',
            ])->setRenderer(function ($row) {
//                return Html::tag('td', ['class' => $this->getRowClasses($row)], date('H:i', $row->ts_modification));
                return $this->linkToModification($row->ts_modification);
            })->setDefaultSortDirection('DESC'),

            $this->createColumn('action', $this->translate('Action'), [
                'action'      => 'ch.action',
                'author'       => 'ch.author',
                'label'       => 'ch.label',
                'object_type' => 'ch.object_type',
            ])->setRenderer(function ($row) {
                return new ConfigHistoryDetails($this->actions, $row);
            }),
        ]);
    }

    protected function linkToModification($ts)
    {
        $l = new LocalTimeFormat();
        return Link::create($l->getShortTime(floor($ts / 1000)), 'eventtracker/history/configuration-change', [
            'ts' => $ts
        ]);
    }

    protected function formatTsHeader($ts)
    {
        $timestamp = (int) ($ts / 1000);
        $now = time();
        $formatter = new LocalDateFormat();
        if ($timestamp > ($now - 120)) {
            return $this->translate('Now');
        }
        if ($timestamp > ($now - 600)) {
            return sprintf($this->translate('%d minutes ago'), floor(($now - $timestamp) / 60));
        }

        return $formatter->getFullDay($timestamp);
    }

    public function prepareQuery()
    {
        $query = $this->db()->select()->from(['ch' => 'config_history'], []);
        return $query->columns($this->getRequiredDbColumns());
    }
}
