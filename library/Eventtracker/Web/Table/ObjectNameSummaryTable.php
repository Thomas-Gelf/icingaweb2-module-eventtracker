<?php

namespace Icinga\Module\Eventtracker\Web\Table;

class ObjectNameSummaryTable extends BaseSummaryTable
{
    protected $searchColumns = [
        'object_name',
    ];

    protected function getMainColumn(): string
    {
        return 'object_name';
    }

    protected function getMainColumnTitle(): string
    {
        return $this->translate('Object Name');
    }
}
