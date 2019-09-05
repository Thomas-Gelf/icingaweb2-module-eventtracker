<?php

namespace Icinga\Module\Eventtracker\Web\Table;

use gipfl\IcingaWeb2\Icon;
use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Table\Extension\MultiSelect;
use gipfl\Translation\TranslationHelper;
use Icinga\Module\Eventtracker\Priority;
use Icinga\Module\Eventtracker\Time;
use Icinga\Module\Eventtracker\Uuid;
use Icinga\Module\Eventtracker\Web\HtmlPurifier;
use ipl\Html\Html;

class EventsTable extends BaseTable
{
    use TranslationHelper;
    use MultiSelect;

    protected $joinedSenders = false;

    protected $compact = false;

    protected $searchColumns = [
        'i.host_name',
        'i.object_name',
        'i.object_class',
        'i.message',
    ];

    protected $noHeader = false;

    public function setNoHeader($setNoHeader = true)
    {
        $this->noHeader = (bool) $setNoHeader;

        return $this;
    }

    public function showCompact($compact = true)
    {
        $this->compact = $compact;

        return $this;
    }

    protected function renderTitleColumns()
    {
        if ($this->noHeader) {
            return null;
        } else {
            return parent::renderTitleColumns();
        }
    }

    protected function initialize()
    {
        $this->enableMultiSelect(
            'eventtracker/issue',
            'eventtracker/issue',
            ['uuid']
        );
        $prioIconRenderer = function ($row) {
            $icons = [
                Priority::HIGHEST => 'up-big',
                Priority::HIGH    => 'up-small',
                Priority::NORMAL  => 'right-small',
                Priority::LOW     => 'down-small',
                Priority::LOWEST  => 'down-big',
            ];

            return Icon::create($icons[$row->priority], [
                'title' => ucfirst($row->priority)
            ]);
        };
        $this->addAvailableColumns([
            $this->createColumn('severity', $this->translate('Severity'), [
                'severity'   => 'i.severity',
                'priority'   => 'i.priority',
                'status'     => 'i.status',
                'timestamp'  => 'i.ts_first_event',
                'issue_uuid' => 'i.issue_uuid',
            ])->setRenderer(function ($row) use ($prioIconRenderer) {
                return $this->formatSeverityColumn($row, $prioIconRenderer);
            })->setSortExpression([
                'i.severity',
                'i.status',
                'i.priority',
                'i.ts_first_event'
            ])->setDefaultSortDirection('DESC'),
            $this->createColumn('priority', $this->translate('Priority'), [
                'priority' => 'i.priority'
            ])->setRenderer(function ($row) use ($prioIconRenderer) {
                return Html::tag('td', ['style' => 'white-space: nowrap'], [
                    $prioIconRenderer($row),
                    $row->priority
                ]);
            })->setDefaultSortDirection('DESC'),
            $this->createColumn('received', $this->translate('Received'), [
                'received' => 'i.ts_first_event'
            ])->setRenderer(function ($row) {
                return Time::agoFormatted($row->received);
            })->setDefaultSortDirection('DESC'),
            $this->createColumn('host_name', $this->translate('Host'), [
                'host_name' => 'i.host_name'
            ])->setSortExpression([
                'i.host_name',
                'i.object_name',
            ]),
            $this->createColumn('object_name', $this->translate('Object'), [
                'object_name'   => 'i.object_name',
                'issue_uuid' => 'i.issue_uuid'
            ])->setRenderer(function ($row) {
                return $this->fixObjectName($row->object_name);
            }),
            $this->createColumn('message', $this->translate('Message'), [
                'severity'      => 'i.severity', // Used by linkToObject
                'issue_uuid'    => 'i.issue_uuid',
                'message'       => 'i.message',
                'host_name'     => 'i.host_name',
                'object_name'   => 'i.object_name',
            ])->setRenderer(function ($row) {
                return $this->formatMessageColumn($row);
            }),
            $this->createColumn('sender_name', $this->translate('Sender'), 's.sender_name'),
        ]);
    }

    protected function formatSeverityColumn($row, $prioIconRenderer)
    {
        $classes = [
            'severity-col',
            $row->severity
        ];
        if ($row->status !== 'open') {
            $classes[] = 'ack';
        }

        if ($this->compact) {
            return Html::tag('td', [
                'class' => $classes
            ], [
                Time::agoFormatted($row->timestamp)
            ]);
        }

        $link = Link::create(substr(strtoupper($row->severity), 0, 4), 'eventtracker/issue', [
            'uuid' => Uuid::toHex($row->issue_uuid)
        ], [
            'title' => \ucfirst($row->severity)
        ]);

        if (! \in_array('priority', $this->getChosenColumnNames())) {
            $link->add($prioIconRenderer($row));
        }

        $td = Html::tag('td', ['class' => $classes], $link);
        if (! \in_array('received', $this->getChosenColumnNames())) {
            $td->add(Time::agoFormatted($row->timestamp));
        }

        return $td;
    }

    protected function formatMessageColumn($row)
    {
        if (\in_array('host_name', $this->getChosenColumnNames())) {
            $host = null;
        } else {
            $host = $row->host_name;
        }
        if (\in_array('object_name', $this->getChosenColumnNames())) {
            $object = null;
        } else {
            $object = $this->fixObjectName($row->object_name);
        }

        if ($host === null && $object === null) {
            $link = null;
        } else {
            if ($host === null) {
                $link = $this->linkToObject($row, $host);
            } elseif ($object === null) {
                $link = $this->linkToObject($row, $object);
            } else {
                $link = $this->linkToObject($row, "$object on $host");
            }
        }
        return $this->renderMessage($row->message, $link, Uuid::toHex($row->issue_uuid));
    }

    protected function renderMessage($message, $link, $id)
    {
        $message = \preg_replace('/\r?\n.+/s', '', $message);
        if ($link === null) {
            return Html::tag('p', ['class' => 'output-line'], HtmlPurifier::process($message));
        } else {
            return Html::tag('td', ['id' => $id], [
                $link,
                $this->compact ? null : Html::tag('p', ['class' => 'output-line'], HtmlPurifier::process($message))
            ]);
        }
    }

    protected function linkToObject($row, $label)
    {
        return Link::create($label, 'eventtracker/issue', [
            'uuid' => Uuid::toHex($row->issue_uuid)
        ], [
            'title' => \ucfirst($row->severity)
        ]);
    }

    protected function fixObjectName($objectName)
    {
        // [Skype] Centralized Logging Service Agent Local logs being deleted and unable to move to network share
        // -> Skype
        // TODO: this doesn't belong here, should happen at event processing timem
        return \preg_replace('/^\[([^\]?]+)\].*/', '\1', $objectName);
    }

    public function getDefaultColumnNames()
    {
        return [
            'severity',
            'message',
        ];
    }

    public function prepareQuery()
    {
        $columns = $this->getRequiredDbColumns();
        $query = $this->db()->select()->from(['i' => 'issue'], []);
        if (array_key_exists('sender_name', $columns)) {
            $query->join(['s' => 'sender'], 's.id = i.sender_id', []);
            $this->joinedSenders = true;
        }

        return $query->columns($this->getRequiredDbColumns());
    }

    public function joinSenders()
    {
        if ($this->joinedSenders === false) {
            $this->getQuery()->join(['s' => 'sender'], 's.id = i.sender_id', []);
            $this->joinedSenders = true;
        }

        return $this;
    }
}
