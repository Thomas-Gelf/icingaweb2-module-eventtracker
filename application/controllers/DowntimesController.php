<?php

namespace Icinga\Module\Eventtracker\Controllers;

use Icinga\Module\Eventtracker\Web\Table\ScheduledDowntimesTable;

class DowntimesController extends Controller
{
    public function scheduledAction()
    {
        $this->addSingleTab($this->translate('Scheduled Downtimes'));
        $table = new ScheduledDowntimesTable($this->db());
        $table->renderTo($this);
    }
}
