<?php

namespace Icinga\Module\Eventtracker\Web\Table;

class HostNameSummaryTable extends BaseSummaryTable
{
    protected $searchColumns = [
        'host_name',
    ];

    protected function getMainColumn()
    {
        return 'host_name';
    }

    protected function getMainColumnTitle()
    {
        return $this->translate('Hostname');
    }
}
