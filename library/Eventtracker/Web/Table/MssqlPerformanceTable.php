<?php

namespace Icinga\Module\Eventtracker\Web\Table;

class MssqlPerformanceTable extends BaseTable
{
    protected $defaultAttributes = [
        'class' => 'common-table'
    ];

    protected $searchColumns = [
        'object_name',
        'counter_name',
        'instance_name',
    ];

    public function prepareQuery()
    {
        return $this->db()
            ->select()
            ->from('sys.dm_os_performance_counters', $this->getRequiredDbColumns());
    }

    protected function initialize()
    {
        $this->addAvailableColumns([
            $this->createColumn('object_name', $this->translate('Object')),
            $this->createColumn('counter_name', $this->translate('Counter')),
            $this->createColumn('instance_name', $this->translate('Instance')),
            $this->createColumn('cntr_value', $this->translate('Value')),
            $this->createColumn('cntr_type', $this->translate('Type')),
        ]);
    }
}
