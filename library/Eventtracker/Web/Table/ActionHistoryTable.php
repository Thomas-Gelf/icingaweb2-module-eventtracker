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
        $actions = $this->db()->fetchPairs($this->db()->select()->from('action', ['uuid', 'label']));
        $this->addAvailableColumns([
            $this->createColumn('ts_done', $this->translate('Time'), [
                'ts_done' => 'ah.ts_done',
                'uuid'    => 'ah.uuid',
            ])->setRenderer(function ($row) {
                return $this->linkToObject($row->uuid, Time::agoFormatted($row->ts_done));
            })->setDefaultSortDirection('DESC'),
            $this->createColumn('message', $this->translate('Action: Message'), [
                'action_uuid' => 'ah.action_uuid',
                'message'     => 'ah.message',
                'success'     => 'ah.success',
            ])->setRenderer(function ($row) use ($actions) {
                return [
                    $row->success === 'y' ? Icon::create('ok', [
                        'class' => 'state-ok'
                    ]) : Icon::create('cancel', [
                        'class' => 'state-critical'
                    ]),
                    ' ',
                    $actions[$row->action_uuid] ?? '(unknown action)',
                    ': ',
                    preg_replace(['/^(.+?)\r?\n.+$/s', '#/shared/PHP/Icinga/modules/incubator/vendor/gipfl/zfdbstore/src/#'], ['\1', ''], $row->message)
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
