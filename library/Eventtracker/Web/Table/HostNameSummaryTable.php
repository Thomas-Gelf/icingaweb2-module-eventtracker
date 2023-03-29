<?php

namespace Icinga\Module\Eventtracker\Web\Table;

class HostNameSummaryTable extends BaseSummaryTable
{
    protected $searchColumns = [
        'host_name',
    ];

    protected function getMainColumn(): string
    {
        return 'host_name';
    }

    protected function getMainColumnTitle(): string
    {
        return $this->translate('Hostname');
    }
}
