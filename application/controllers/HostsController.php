<?php

namespace Icinga\Module\Eventtracker\Controllers;

use gipfl\IcingaWeb2\CompatController;
use Icinga\Module\Eventtracker\DbFactory;
use Icinga\Module\Eventtracker\Web\Table\HostNameSummaryTable;
use Icinga\Module\Eventtracker\Web\Widget\SummaryTabs;

class HostsController extends CompatController
{
    public function indexAction()
    {
        $this->addTitle($this->translate('Issue Summary by Hostname'));
        $this->setAutorefreshInterval(10);
        (new HostNameSummaryTable(DbFactory::db()))->renderTo($this);
        $this->tabs(new SummaryTabs())->activate('hosts');
    }
}
