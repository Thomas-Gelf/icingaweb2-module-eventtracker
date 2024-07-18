<?php

namespace Icinga\Module\Eventtracker\Web\Table;

use gipfl\Format\LocalDateFormat;
use gipfl\IcingaWeb2\Icon;
use gipfl\IcingaWeb2\Link;
use gipfl\Translation\TranslationHelper;
use Icinga\Module\Eventtracker\IssueHistory;
use Icinga\Module\Eventtracker\Uuid;
use Icinga\Module\Eventtracker\Web\HtmlPurifier;
use ipl\Html\Html;
use ipl\Html\HtmlElement;

class IssueHistoryTable extends BaseTable
{
    use TranslationHelper;

    protected $searchColumns = [
        'i.host_name',
        'i.object_name',
        'i.object_class',
        'i.message',
        'i.ticket_ref',
    ];

    protected $compact = false;

    public function showCompact($compact = true)
    {
        $this->compact = $compact;
        if ($compact) {
            $this->addAttributes(['class' => 'compact-table']);
        }

        return $this;
    }

    protected function renderTitleColumns()
    {
        return null;
    }

    public function renderRow($row): HtmlElement
    {
        if (isset($row->ts)) {
            $this->renderDayIfNew($this->formatTsHeader($row->ts), count($this->getChosenColumns()));
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
        if ($timestamp > ($now - 120)) {
            return $this->translate('Now');
        }
        if ($timestamp > ($now - 600)) {
            return sprintf($this->translate('%d minutes ago'), floor(($now - $timestamp) / 60));
        }

        return $formatter->getFullDay($timestamp);
    }

    protected function initialize()
    {
        $this->addAvailableColumns([
            $this->createColumn('ts', $this->translate('Closed'), [
                'ts' => 'i.ts_last_modified'
            ])->setRenderer(function ($row) {
                return Html::tag('td', [
                    'class' => $this->getRowClasses($row)
                ], date('H:i', $row->ts));
            })->setDefaultSortDirection('DESC'),
            $this->createColumn('message', $this->translate('Message'), [
                'priority'     => 'i.priority',
                'timestamp'    => 'i.ts_first_event',
                'severity'     => 'i.severity', // Used by linkToObject
                'issue_uuid'   => 'i.issue_uuid',
                'message'      => 'i.message',
                'host_name'    => 'i.host_name',
                'object_name'  => 'i.object_name',
                'close_reason' => 'i.close_reason',
                'closed_by'    => 'i.closed_by',
                'ticket_ref'   => 'i.ticket_ref',
            ])->setRenderer(function ($row) {
                return $this->formatMessageColumn($row);
            }),
        ]);
    }

    protected function renderCloseDetails($row)
    {
        switch ($row->close_reason) {
            case IssueHistory::REASON_MANUAL:
                if ($row->closed_by === null) {
                    return [Icon::create('thumbs-up'), $this->translate('Closed manually')];
                }
                return [Icon::create('thumbs-up'), sprintf($this->translate('Closed by %s'), $row->closed_by)];
            case IssueHistory::REASON_RECOVERY:
                return [Icon::create('ok'), $this->translate('Recovered')];
            case IssueHistory::REASON_EXPIRATION:
                return [Icon::create('clock'), $this->translate('Expired')];
            case null:
                return [Icon::create('help'), $this->translate('Unknown')];
            default:
                return [Icon::create('help'), $row->close_reason . ' (invalid)'];
        }
    }

    protected function getRowClasses($row): array
    {
        return [
            'severity-col',
            'ack',
            $row->severity
        ];
    }

    protected function formatMessageColumn($row)
    {
        $host = $row->host_name;
        $object = $row->object_name;

        if ($host === null && $object === null) {
            $link = null;
        } else {
            if ($host === null) {
                $link = $this->linkToObject($row, $object);
            } elseif ($object === null) {
                $link = $this->linkToObject($row, $host);
            } else {
                $link = $this->linkToObject($row, "$object on $host");
            }
        }
        $el = $this->renderMessage($row->message, $link, Uuid::toHex($row->issue_uuid));
        $closeInfo = Html::tag('span', ['style' => 'font-style: italic'], $this->renderCloseDetails($row));
        if ($el->getTag() === 'td') {
            $el->add($closeInfo);
            return $el;
        } else {
            return [
                $el,
                $closeInfo
            ];
        }
    }

    protected function renderMessage($message, $link, $id)
    {
        $message = \preg_replace('/\r?\n.+/s', '', $message);
        if ($link === null) {
            return Html::tag('p', ['class' => 'output-line'], HtmlPurifier::process($message));
        } else {
            return Html::tag('td', ['id' => $id], [
                $link,
                $this->compact
                    ? ': ' . preg_replace('/\n.+/s', '', \strip_tags($message))
                    : Html::tag('p', ['class' => 'output-line'], HtmlPurifier::process($message))
            ]);
        }
    }

    protected function linkToObject($row, $label)
    {
        return Link::create($label, 'eventtracker/issue', [
            'uuid' => Uuid::toHex($row->issue_uuid)
        ], [
            'title' => \ucfirst($row->severity),
            'style' => 'font-weight: 550;'
        ]);
    }

    public function getDefaultColumnNames(): array
    {
        return [
            'ts',
            'message',
        ];
    }

    public function prepareQuery()
    {
        $query = $this->db()->select()->from(['i' => 'issue_history'], []);
        return $query->columns($this->getRequiredDbColumns());
    }
}
