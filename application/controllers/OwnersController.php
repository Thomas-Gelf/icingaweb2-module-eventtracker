<?php

namespace Icinga\Module\Eventtracker\Controllers;

use gipfl\IcingaWeb2\CompatController;
use Icinga\Module\Eventtracker\DbFactory;
use Icinga\Module\Eventtracker\Web\Table\OwnerSummaryTable;
use Icinga\Module\Eventtracker\Web\Widget\SummaryTabs;

class OwnersController extends CompatController
{
    public function indexAction()
    {
        $this->addTitle($this->translate('Issue Summary by Owner'));
        $this->setAutorefreshInterval(10);
        (new OwnerSummaryTable(DbFactory::db()))->renderTo($this);
        $this->tabs(new SummaryTabs())->activate('objects');
    }
}
