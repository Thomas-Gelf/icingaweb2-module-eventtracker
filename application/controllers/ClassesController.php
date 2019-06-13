<?php

namespace Icinga\Module\Eventtracker\Controllers;

use gipfl\IcingaWeb2\CompatController;
use Icinga\Module\Eventtracker\DbFactory;
use Icinga\Module\Eventtracker\Web\Table\ObjectClassSummaryTable;
use Icinga\Module\Eventtracker\Web\Widget\SummaryTabs;

class ClassesController extends CompatController
{
    public function indexAction()
    {
        $this->addTitle($this->translate('Issue Summary by Object Class'));
        $this->setAutorefreshInterval(10);
        (new ObjectClassSummaryTable(DbFactory::db()))->renderTo($this);
        $this->tabs(new SummaryTabs())->activate('classes');
    }
}
