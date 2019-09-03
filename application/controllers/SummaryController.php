<?php

namespace Icinga\Module\Eventtracker\Controllers;

use gipfl\IcingaWeb2\CompatController;
use Icinga\Module\Eventtracker\DbFactory;
use Icinga\Module\Eventtracker\Web\Table\HostNameSummaryTable;
use Icinga\Module\Eventtracker\Web\Table\ObjectClassSummaryTable;
use Icinga\Module\Eventtracker\Web\Table\ObjectNameSummaryTable;
use Icinga\Module\Eventtracker\Web\Table\OwnerSummaryTable;
use Icinga\Module\Eventtracker\Web\Widget\SummaryTabs;

class SummaryController extends CompatController
{
    public function classesAction()
    {
        $this->addTitle($this->translate('Issue Summary by Object Class'));
        $this->setAutorefreshInterval(10);
        (new ObjectClassSummaryTable(DbFactory::db()))->renderTo($this);
        $this->tabs(new SummaryTabs())->activate('classes');
    }

    public function ownersAction()
    {
        $this->addTitle($this->translate('Issue Summary by Owner'));
        $this->setAutorefreshInterval(10);
        (new OwnerSummaryTable(DbFactory::db()))->renderTo($this);
        $this->tabs(new SummaryTabs())->activate('objects');
    }

    public function hostsAction()
    {
        $this->addTitle($this->translate('Issue Summary by Hostname'));
        $this->setAutorefreshInterval(10);
        (new HostNameSummaryTable(DbFactory::db()))->renderTo($this);
        $this->tabs(new SummaryTabs())->activate('hosts');
    }

    public function objectsAction()
    {
        $this->addTitle($this->translate('Issue Summary by Object Name'));
        $this->setAutorefreshInterval(10);
        (new ObjectNameSummaryTable(DbFactory::db()))->renderTo($this);
        $this->tabs(new SummaryTabs())->activate('objects');
    }
}
