<?php

namespace Icinga\Module\Eventtracker\Web\Table;

use gipfl\IcingaWeb2\Link;
use gipfl\Translation\TranslationHelper;
use Ramsey\Uuid\Uuid;

class ConfiguredChannelsTable extends BaseTable
{
    use TranslationHelper;

    protected function initialize()
    {
        $this->addAvailableColumns([
            $this->createColumn('label', $this->translate('Channel'), 'label')
                ->setRenderer(function ($row) {
                    return Link::create($row->label, 'eventtracker/configuration/channel', [
                        'uuid' => Uuid::fromBytes($row->uuid)->toString()
                    ]);
                }),
        ]);
    }

    public function prepareQuery()
    {
        $query = $this->db()
            ->select()
            ->from(['c' => 'channel'], [
                'uuid',
                'label',
            ])->order('label');

        return $query;
    }
}
