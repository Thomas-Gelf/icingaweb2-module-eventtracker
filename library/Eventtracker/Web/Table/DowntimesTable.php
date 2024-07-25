<?php

namespace Icinga\Module\Eventtracker\Web\Table;

use gipfl\Format\LocalDateFormat;
use gipfl\IcingaWeb2\Icon;
use gipfl\IcingaWeb2\Link;
use gipfl\Translation\TranslationHelper;
use gipfl\Web\Table\NameValueTable;
use Icinga\Module\Eventtracker\Engine\Downtime\DowntimeCalculated;
use Icinga\Module\Eventtracker\Engine\Downtime\HostList;
use Icinga\Module\Eventtracker\Time;
use ipl\Html\DeferredText;
use ipl\Html\Html;
use ipl\Html\HtmlElement;

class DowntimesTable extends BaseTable
{
    use TranslationHelper;

    protected $currentDayString = '';

    protected $searchColumns = [];

    protected $requestedHostLists = [];

    public function renderRow($row): HtmlElement
    {
        if (isset($row->ts_expected_start)) {
            $this->renderDayIfNew($this->formatTsHeader($row->ts_expected_start), count($this->getChosenColumns()));
            $this->currentDayString = date('Y-m-d', $row->ts_expected_start / 1000);
        }

        return parent::renderRow($row);
    }

    protected function tsMatchesCurrentDay(int $ts): bool
    {
        return $this->currentDayString === date('Y-m-d', $ts / 1000);
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
        $formatter = new LocalDateFormat();
        return $formatter->getFullDay($timestamp);

        $now = time();
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

    protected function formatRelatedTimestamp(?int $ts): string
    {
        // Hint: TS_NEVER is treated as NULL, NULL is not allowed
        if ($ts === null || $ts === DowntimeCalculated::TS_NEVER) {
            return $this->translate('never');
        }
        if ($this->tsMatchesCurrentDay($ts)) {
            return $this->getTimeFormatter()->getShortTime($ts / 1000);
        }

        return $this->getDateFormatter()->getFullDay($ts / 1000)
            . ', '
            . $this->getTimeFormatter()->getShortTime($ts / 1000);
    }

    protected function initialize()
    {
        $this->addAvailableColumns([
            $this->createColumn('ts_expected_start', $this->translate('Downtime Rule'), [
                'is_active'         => 'dc.is_active',
                'ts_started'        => 'dc.ts_started',
                'ts_expected_start' => 'dc.ts_expected_start',
                'ts_expected_end'   => 'dc.ts_expected_end',
                'host_list_uuid'    => 'dr.host_list_uuid',
                'host_list_label'   => 'hl.label',
            ])->setRenderer(function ($row) {
                $result = [];

                $infoTable = new NameValueTable();
                if ($row->is_active === 'y') {
                    $result[] = Icon::create('plug', ['class' => ['downtime-icon', 'active-downtime']]);
                    $infoTable->addNameValueRow(
                        $this->translate('Started:'),
                        $this->formatRelatedTimestamp($row->ts_started)
                    );
                } else {
                    $result[] = Icon::create('history', ['class' => 'downtime-icon']);
                    $infoTable->addNameValueRow(
                        $this->translate('Starts:'),
                        $this->formatRelatedTimestamp($row->ts_expected_start)
                    );
                }
                $infoTable->addNameValueRow(
                    $this->translate('Ends:'),
                    $this->formatRelatedTimestamp($row->ts_expected_end)
                );
                if ($row->host_list_uuid) {
                    $infoTable->addNameValueRow($this->translate('Host list:'), Html::sprintf(
                        $this->translate('%s (currently %s hosts)'),
                        $row->host_list_label,
                        $this->deferredHostListCount($row->host_list_uuid)
                    ));
                }
                $result[] = Link::create($row->label, '#');
                $result[] = Html::tag('br');
                $result[] = $infoTable;

                return $result;
            }),
            $this->createColumn('label', $this->translate('Downtime Rule'), [
                'label'             => 'dr.label',
            ])->setRenderer(function ($row) {
                return ; // ??
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

    protected function deferredHostListCount(string $hostListUuid): DeferredText
    {
        if (! array_key_exists($hostListUuid, $this->requestedHostLists)) {
            $this->requestedHostLists[$hostListUuid] = null;
        }

        return new DeferredText(function () use ($hostListUuid) {
            if ($this->requestedHostLists[$hostListUuid] === null) {
                $this->fetchRequestedHostListCounts();
            }

            return $this->requestedHostLists[$hostListUuid] ?? '??';
        });
    }

    protected function fetchRequestedHostListCounts()
    {
        if (empty($this->requestedHostLists)) {
            return;
        }

        foreach ($this->requestedHostLists as $key) {
            $this->requestedHostLists[$key] = 0;
        }

        foreach ($this->db()->fetchPairs($this->db()->select()->from(['hl' => HostList::TABLE_NAME], [
            'uuid',
            'cnt' => 'COUNT(*)'
        ])->joinLeft(['hlm' => 'host_list_member'], 'hl.uuid = hlm.list_uuid', [])
            ->where('uuid IN (?)', array_keys($this->requestedHostLists))) as $uuid => $count) {
            $this->requestedHostLists[$uuid] = $count;
        }
    }

    public function prepareQuery()
    {
        return $this->db()->select()
            ->from(['dc' => 'downtime_calculated'], $this->getRequiredDbColumns())
            ->join(['dr' => 'downtime_rule'], 'dr.config_uuid = dc.rule_config_uuid', [])
            ->joinLeft(['hl' => 'host_list'], 'dr.host_list_uuid = hl.uuid', [])
            ->order('dc.ts_expected_start ASC');
    }
}
