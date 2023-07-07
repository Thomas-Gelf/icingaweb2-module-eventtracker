<?php

namespace Icinga\Module\Eventtracker\Web\Table;

use gipfl\IcingaWeb2\Icon;
use gipfl\IcingaWeb2\Link;
use gipfl\Translation\TranslationHelper;
use Icinga\Module\Eventtracker\Time;
use Ramsey\Uuid\Uuid;

class ActionHistoryTable extends BaseTable
{
    use TranslationHelper;

    protected $searchColumns = [
        'i.host_name',
        'i.object_name',
        'i.object_class',
        'i.ticket_ref',
        'ah.message'
    ];

    protected function initialize()
    {
        $this->addAvailableColumns([
            $this->createColumn('ts_done', $this->translate('Time'), [
                'ts_done' => 'ah.ts_done',
                'uuid'    => 'ah.uuid',
            ])->setRenderer(function ($row) {
                return $this->linkToObject($row->uuid, Time::agoFormatted($row->ts_done));
            })->setDefaultSortDirection('DESC'),
            $this->createColumn('message', $this->translate('Message'), [
                'message'       => 'ah.message',
                'success'       => 'ah.success',
            ])->setRenderer(function ($row) {
                return [
                    $row->success === 'y' ? Icon::create('ok', [
                        'class' => 'state-ok'
                    ]) : Icon::create('cancel', [
                        'class' => 'state-critical'
                    ]),
                    ' ',
                    preg_replace('/^(.+?)\r?\n.+$/s', '\1', $row->message)
                ];
            }),
            $this->createColumn('host_name', $this->translate('Host'), [
                'host_name' => 'i.host_name'
            ])->setSortExpression([
                'i.host_name',
                'i.object_name',
            ]),
            $this->createColumn('object_name', $this->translate('Object'), [
                'object_name'   => 'i.object_name',
                'issue_uuid' => 'i.issue_uuid'
            ]),
        ]);
    }

    protected function linkToObject($uuid, $label)
    {
        return Link::create($label, 'eventtracker/actionhistory/entry', [
            'uuid' => Uuid::fromBytes($uuid)->toString()
        ], [
            'title' => $label
        ]);
    }

    public function getDefaultColumnNames()
    {
        return [
            'ts_done',
            'host_name',
            'object_name',
            'message',
        ];
    }

    public function prepareQuery()
    {
        $query = $this->db()->select()->from(['ah' => 'action_history'], []);
        $query->joinLeft(['i' => 'issue'], 'i.issue_uuid = ah.issue_uuid', []);
        return $query->columns($this->getRequiredDbColumns());
    }
}
