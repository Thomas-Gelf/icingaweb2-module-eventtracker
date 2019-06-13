<?php

namespace Icinga\Module\Eventtracker\Controllers;

use gipfl\IcingaWeb2\CompatController;
use Icinga\Module\Eventtracker\DbFactory;
use Icinga\Module\Eventtracker\Web\Table\ObjectNameSummaryTable;
use Icinga\Module\Eventtracker\Web\Widget\SummaryTabs;

class ObjectsController extends CompatController
{
    public function indexAction()
    {
        $this->addTitle($this->translate('Issue Summary by Object Name'));
        $this->setAutorefreshInterval(10);
        (new ObjectNameSummaryTable(DbFactory::db()))->renderTo($this);
        $this->tabs(new SummaryTabs())->activate('objects');
    }
}
