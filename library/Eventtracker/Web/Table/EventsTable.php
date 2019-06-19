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

            if ($row->priority === Priority::NORMAL) {
                // return '';
            }

            return Icon::create($icons[$row->priority], [
                'title' => ucfirst($row->priority)
            ]);
        };
        $this->addAvailableColumns([
            $this->createColumn('severity', $this->translate('Sev'), [
                'severity'      => 'i.severity',
                'priority'      => 'i.priority',
                'status'        => 'i.status',
                'timestamp'     => 'i.ts_first_event',
                'issue_uuid' => 'i.issue_uuid',
            ])->setRenderer(function ($row) use ($prioIconRenderer) {
                $classes = [
                    'severity-col',
                    $row->severity
                ];
                if ($row->status !== 'open') {
                    $classes[] = 'ack';
                }
                $link = Link::create(substr(strtoupper($row->severity), 0, 4), 'eventtracker/issue', [
                    'uuid' => Uuid::toHex($row->issue_uuid)
                ], [
                    'title' => ucfirst($row->severity)
                ]);

                if (! in_array('priority', $this->getChosenColumnNames())) {
                    $link->add($prioIconRenderer($row));
                }

                return Html::tag('td', [
                    'class' => $classes
                ], [
                    $link,
                    Time::agoFormatted($row->timestamp)
                ]);
            })->setSortExpression([
                'severity',
                'status',
                'priority',
                'timestamp'
            ]),
            $this->createColumn('priority', $this->translate('Priority'), [
                'priority' => 'i.priority'
            ])->setRenderer($prioIconRenderer),
            $this->createColumn('ts_first_event', $this->translate('Received'))->setRenderer(function ($row) {
                return Time::agoFormatted($row->ts_first_event);
            }),
            $this->createColumn('host_name', $this->translate('Host'), [
                'host_name' => 'i.host_name'
            ]),
            $this->createColumn('object_name', $this->translate('Object'), [
                'object_name'   => 'i.object_name',
                'issue_uuid' => 'i.issue_uuid'
            ])->setRenderer(function ($row) {
                return $this->fixObjectName($row->object_name);
            }),
            $this->createColumn('message', $this->translate('Message'), [
                'severity'      => 'i.severity',
                'issue_uuid' => 'i.issue_uuid',
                'message'       => 'i.message',
                'host_name'     => 'i.host_name',
                'object_name'   => 'i.object_name',
            ])->setRenderer(function ($row) {
                $hex = Uuid::toHex($row->issue_uuid);
                if (in_array('host_name', $this->getChosenColumnNames())) {
                    $host = null;
                } else {
                    $host = $row->host_name;
                }
                if (in_array('object_name', $this->getChosenColumnNames())) {
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
                $message = preg_replace('/\r?\n.+/s', '', $row->message);
                if ($link === null) {
                    return HtmlPurifier::process($message);
                } else {
                    return Html::tag('td', ['id' => $hex], [
                        $link,
                        Html::tag('p', ['class' => 'output-line'], HtmlPurifier::process($message))
                    ]);
                }
            }),
        ]);
    }

    protected function linkToObject($row, $label)
    {
        return Link::create($label, 'eventtracker/issue', [
            'uuid' => Uuid::toHex($row->issue_uuid)
        ], [
            'title' => ucfirst($row->severity)
        ]);
    }

    protected function fixObjectName($objectName)
    {
        // [Skype] Centralized Logging Service Agent Local logs being deleted and unable to move to network share
        // -> Skype
        return preg_replace('/^\[([^\]?]+)\].*/', '\1', $objectName);
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
        return $this->db()->select()->from(['i' => 'issue'], $this->getRequiredDbColumns());
    }
}
