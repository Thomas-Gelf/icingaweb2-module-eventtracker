<?php

namespace Icinga\Module\Eventtracker\Web\Table;

use gipfl\IcingaWeb2\Icon;
use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Table\Extension\MultiSelect;
use gipfl\Translation\TranslationHelper;
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
            'eventtracker/event',
            'eventtracker/event',
            ['uuid']
        );
        $prioIconRenderer = function ($row) {
            $icons = [
                'highest' => 'up-big',
                'high'    => 'up-small',
                'normal'  => 'right-small',
                'low'     => 'down-small',
                'lowest'  => 'down-big',
            ];

            if ($row->priority === 'normal') {
                // return '';
            }

            return Icon::create($icons[$row->priority], [
                'title' => ucfirst($row->priority)
            ]);
        };
        $this->addAvailableColumns([
            $this->createColumn('severity', $this->translate('Sev'), [
                'severity'      => 'i.severity',
                'incident_uuid' => 'i.incident_uuid',
                'priority'      => 'i.priority',
            ])->setRenderer(function ($row) use ($prioIconRenderer) {
                $classes = [
                    "severity-col $row->severity"
                ];
                $link = Link::create(substr(strtoupper($row->severity), 0, 4), 'eventtracker/event', [
                    'uuid' => Uuid::toHex($row->incident_uuid)
                ], [
                    'title' => ucfirst($row->severity)
                ]);

                if (! in_array('priority', $this->getChosenColumnNames())) {
                    $link->add($prioIconRenderer($row));
                }

                return Html::tag('td', ['class' => $classes], $link);
            }),
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
                'incident_uuid' => 'i.incident_uuid'
            ])->setRenderer(function ($row) {
                return $row->object_name;
            }),
            $this->createColumn('message', $this->translate('Message'), [
                'severity'      => 'i.severity',
                'incident_uuid' => 'i.incident_uuid',
                'message'       => 'i.message',
                'object_name'   => 'i.object_name',
            ])->setRenderer(function ($row) {
                $hex = Uuid::toHex($row->incident_uuid);
                $link = Link::create(substr(strtoupper($row->severity), 0, 4), 'eventtracker/event', [
                    'uuid' => $hex
                ], [
                    'title' => ucfirst($row->severity)
                ]);
                if (in_array('object_name', $this->getChosenColumnNames())) {
                    return HtmlPurifier::process($row->message);
                } else {
                    return Html::tag('td', ['id' => $hex], Html::sprintf(
                        '%s: %s',
                        $link,
                        HtmlPurifier::process($row->message)
                    ));
                }
            }),
        ]);
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
        return $this->db()->select()->from(['i' => 'incident'], $this->getRequiredDbColumns());
    }
}
