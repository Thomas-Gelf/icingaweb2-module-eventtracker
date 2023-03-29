<?php

namespace Icinga\Module\Eventtracker\Web\Table;

class OwnerSummaryTable extends BaseSummaryTable
{
    protected $searchColumns = [
        'owner',
    ];

    protected function getMainColumn(): string
    {
        return 'owner';
    }

    protected function getMainColumnTitle(): string
    {
        return $this->translate('Owner');
    }
}
