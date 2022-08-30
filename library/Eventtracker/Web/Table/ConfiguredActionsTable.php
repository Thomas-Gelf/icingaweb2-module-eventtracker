<?php

namespace Icinga\Module\Eventtracker\Web\Table;

use gipfl\IcingaWeb2\Link;
use gipfl\Translation\TranslationHelper;
use Ramsey\Uuid\Uuid;

class ConfiguredActionsTable extends BaseTable
{
    use TranslationHelper;

    protected function initialize()
    {
        $this->addAvailableColumns([
            $this->createColumn('label', $this->translate('Action'), 'label')
                ->setRenderer(function ($row) {
                    return Link::create($row->label, 'eventtracker/configuration/action', [
                        'uuid' => Uuid::fromBytes($row->uuid)->toString()
                    ]);
                }),
            $this->createColumn('enabled', $this->translate('Enabled'), 'enabled')
                ->setRenderer(function ($row) {
                    switch ($row->enabled) {
                        case 'y':
                            return $this->translate('Yes');
                        case 'n':
                            return $this->translate('No');
                    }
                }),
        ]);
    }

    public function prepareQuery()
    {
        $query = $this->db()
            ->select()
            ->from(['a' => 'action'], [
                'uuid',
                'label',
                'enabled'
            ])->order('label');

        return $query;
    }
}
