<?php

namespace Icinga\Module\Eventtracker\Web\Table;

class ObjectClassSummaryTable extends BaseSummaryTable
{
    protected $searchColumns = [
        'object_class',
    ];

    protected function getMainColumn(): string
    {
        return 'object_class';
    }

    protected function getMainColumnTitle(): string
    {
        return $this->translate('Object Class');
    }
}
