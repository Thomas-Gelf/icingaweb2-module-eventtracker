<?php

namespace Icinga\Module\Eventtracker\Web\Table;

use gipfl\IcingaWeb2\Link;
use gipfl\Translation\TranslationHelper;
use Ramsey\Uuid\Uuid;

class ConfiguredHostListsTable extends BaseTable
{
    use TranslationHelper;

    protected function initialize()
    {
        $this->addAvailableColumns([
            $this->createColumn('label', $this->translate('Host list'), 'label')
                ->setRenderer(function ($row) {
                    return Link::create($row->label, 'eventtracker/configuration/hostlist', [
                        'uuid' => Uuid::fromBytes($row->uuid)->toString()
                    ]);
                }),
        ]);
    }

    public function prepareQuery()
    {
        return $this->db()->select()->from(['hl' => 'host_list'], [
            'uuid',
            'label',
        ])->order('label');
    }
}
