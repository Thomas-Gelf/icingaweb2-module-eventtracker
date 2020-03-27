<?php

namespace Icinga\Module\Eventtracker\Controllers;

use Icinga\Module\Eventtracker\Web\Tabs\ScomTabs;
use Icinga\Module\Eventtracker\Web\Table\MssqlPerformanceTable;
use Icinga\Module\Eventtracker\Web\Table\MssqlProcessesTable;

class MssqlController extends Controller
{
    public function performanceAction()
    {
        $this->tabs(new ScomTabs())->activate('perfcounters');
        $this->addTitle('MSSQL Server Performance Counters');
        $table = new MssqlPerformanceTable($this->getScomDb(), $this->url());
        $this->addTable($table, 'object_name');
    }

    public function processesAction()
    {
        $this->tabs(new ScomTabs())->activate('processes');
        $this->addTitle('MSSQL Server Processes');
        $table = new MssqlProcessesTable($this->getScomDb(), $this->url());
        $this->addTable($table, 'session_id');
    }
}
