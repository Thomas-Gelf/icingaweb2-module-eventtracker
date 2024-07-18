<?php

namespace Icinga\Module\Eventtracker\Web\Table;

class SenderSummaryTable extends BaseSummaryTable
{
    protected $searchColumns = [
        's.sender_name',
        's.implementation',
    ];

    protected function getMainColumn(): string
    {
        return 's.sender_name';
    }

    protected function getMainColumnAlias(): string
    {
        return 'sender_name';
    }

    protected function getMainColumnTitle(): string
    {
        return $this->translate('Sender (Old)');
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
