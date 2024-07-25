<?php

namespace Icinga\Module\Eventtracker\Controllers;

use Icinga\Module\Eventtracker\Web\Table\DowntimesTable;

class DowntimesController extends Controller
{
    public function indexAction()
    {
        $this->setAutorefreshInterval(15);
        $this->addSingleTab($this->translate('Downtimes'));
        $table = new DowntimesTable($this->db());
        $table->renderTo($this);
    }
}
