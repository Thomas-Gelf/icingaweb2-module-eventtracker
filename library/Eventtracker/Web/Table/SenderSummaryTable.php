<?php

namespace Icinga\Module\Eventtracker\Web\Table;

class SenderSummaryTable extends BaseSummaryTable
{
    protected $searchColumns = [
        's.sender_name',
        's.implementation',
    ];

    protected function getMainColumn()
    {
        return 's.sender_name';
    }

    protected function getMainColumnAlias()
    {
        return 'sender_name';
    }

    protected function getMainColumnTitle()
    {
        return $this->translate('Sender');
    }

    public function prepareQuery()
    {
        $query = parent::prepareQuery();
        $query->join(
            ['s' => 'sender'],
            'i.sender_id = s.id',
            []
        );

        return $query;
    }
}
