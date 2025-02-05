<?php

namespace Icinga\Module\Eventtracker\Web\Table;

use gipfl\IcingaWeb2\Link;
use gipfl\Translation\TranslationHelper;
use Icinga\Module\Eventtracker\Time;

class ConfigurationHistoryTable extends BaseTable
{
    use TranslationHelper;

    protected $searchColumns = [
        'ch.action',
    ];

    protected function initialize()
    {
        $this->addAvailableColumns([
            $this->createColumn('ts_modification', $this->translate('Time'), [
                'ts_modification' => 'ch.ts_modification',
                'object_uuid' => 'ch.object_uuid',
            ])->setRenderer(function ($row) {
                return $this->linkToModification($row->ts_modification);
            })->setDefaultSortDirection('DESC'),
            $this->createColumn('action', $this->translate('Action'), [
                'action'      => 'ch.action',
                'label'       => 'ch.label',
                'object_type' => 'ch.object_type',
            ])->setRenderer(function ($row) {
                return sprintf('%s %s "%s"', $row->action, $row->object_type, $row->label);
            }),
            $this->createColumn('author', $this->translate('Author')),
        ]);
    }

    protected function linkToModification($ts)
    {
        return Link::create(Time::agoFormatted($ts), 'eventtracker/history/configuration-change', [
            'ts' => $ts
        ]);
    }

    public function prepareQuery()
    {
        $query = $this->db()->select()->from(['ch' => 'config_history'], []);
        return $query->columns($this->getRequiredDbColumns());
    }
}
