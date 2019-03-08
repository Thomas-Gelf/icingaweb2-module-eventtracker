<?php

namespace Icinga\Module\Eventtracker\Web\Table;

use gipfl\IcingaWeb2\Icon;
use gipfl\IcingaWeb2\Link;
use gipfl\Translation\TranslationHelper;
use Icinga\Module\Eventtracker\Time;
use Icinga\Module\Eventtracker\Uuid;
use ipl\Html\Html;

class EventsTable extends BaseTable
{
    use TranslationHelper;

    protected $searchColumns = [
        'i.host_name',
        'i.object_name',
        'i.message',
    ];

    protected function initialize()
    {
        $this->addAvailableColumns([
            $this->createColumn('severity', $this->translate('Sev'), [
                'severity'      => 'i.severity',
                'incident_uuid' => 'i.incident_uuid'
            ])->setRenderer(function ($row) {
                $classes = [
                    "severity-col $row->severity"
                ];
                $link = Link::create(substr(strtoupper($row->severity), 0, 4), 'eventtracker/event', [
                    'uuid' => Uuid::toHex($row->incident_uuid)
                ], [
                    'title' => ucfirst($row->severity)
                ]);

                return Html::tag('td', ['class' => $classes], $link);
            }),
            $this->createColumn('priority', $this->translate('Priority'))->setRenderer(function ($row) {
                $icons = [
                    'highest' => 'up-big',
                    'high'    => 'up-small',
                    'normal'  => 'right-small',
                    'low'     => 'down-small',
                    'lowest'  => 'down-big',
                ];

                return Icon::create($icons[$row->priority], [
                    'title' => ucfirst($row->priority)
                ]);
            }),
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
                'message'     => 'i.message',
                'object_name' => 'i.object_name',
            ])->setRenderer(function ($row) {
                if (in_array('object_name', $this->getChosenColumnNames())) {
                    return $row->message;
                } else {
                    return Html::sprintf(
                        '%s: %s',
                        Html::tag('strong', $row->object_name),
                        $row->message
                    );
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
