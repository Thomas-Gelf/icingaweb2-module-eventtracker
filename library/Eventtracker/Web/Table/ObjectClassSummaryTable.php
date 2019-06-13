<?php

namespace Icinga\Module\Eventtracker\Web\Table;

class ObjectClassSummaryTable extends BaseSummaryTable
{
    protected $searchColumns = [
        'object_class',
    ];

    protected function getMainColumn()
    {
        return 'object_class';
    }

    protected function getMainColumnTitle()
    {
        return $this->translate('Object Class');
    }
}
