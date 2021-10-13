<?php

namespace Icinga\Module\Eventtracker\Controllers;

use Icinga\Module\Eventtracker\Web\Table\OwnerSummaryTable;
use Icinga\Module\Eventtracker\Web\Widget\SummaryTabs;

class OwnersController extends Controller
{
    public function indexAction()
    {
        $this->addTitle($this->translate('Issue Summary by Owner'));
        $this->setAutorefreshInterval(10);
        (new OwnerSummaryTable($this->db()))->renderTo($this);
        $this->tabs(new SummaryTabs())->activate('objects');
    }
}
