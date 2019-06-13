<?php

namespace Icinga\Module\Eventtracker\Web\Table;

class OwnerSummaryTable extends BaseSummaryTable
{
    protected $searchColumns = [
        'owner',
    ];

    protected function getMainColumn()
    {
        return 'owner';
    }

    protected function getMainColumnTitle()
    {
        return $this->translate('Owner');
    }
}
