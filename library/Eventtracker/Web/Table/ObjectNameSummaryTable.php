<?php

namespace Icinga\Module\Eventtracker\Web\Table;

class ObjectNameSummaryTable extends BaseSummaryTable
{
    protected $searchColumns = [
        'object_name',
    ];

    protected function getMainColumn()
    {
        return 'object_name';
    }

    protected function getMainColumnTitle()
    {
        return $this->translate('Object Name');
    }
}
