<?php

namespace Icinga\Module\Eventtracker\Controllers;

use Icinga\Module\Eventtracker\Web\Table\MssqlProcessesTable;
use Icinga\Module\Eventtracker\Web\Table\ScomAlertsTable;
use Icinga\Module\Eventtracker\Web\Tabs\ScomTabs;

class ScomController extends Controller
{
    public function alertsAction()
    {
        $this->tabs(new ScomTabs())->activate('alerts');
        $this->addTitle('SCOM Alerts');
        $table = new ScomAlertsTable($this->getScomDb(), $this->url());
        $this->addTable($table, 'entity_name');
    }

    public function processesAction()
    {
        $this->addSingleTab('Processes');
        $this->addTitle('MSSQL Server Processes');
        $table = new MssqlProcessesTable($this->getScomDb());
        $table->renderTo($this);
    }
}
