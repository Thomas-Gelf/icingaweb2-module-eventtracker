<?php

namespace Icinga\Module\Eventtracker\Controllers;

use gipfl\IcingaWeb2\CompatController;
use Icinga\Authentication\Auth;
use Icinga\Module\Eventtracker\DbFactory;
use Icinga\Module\Eventtracker\Web\Table\EventsTable;
use Icinga\Module\Eventtracker\Web\Widget\AdditionalTableActions;

class EventsController extends CompatController
{
    public function indexAction()
    {
        $db = DbFactory::db();
        $this->addSingleTab('Events');
        $this->addTitle('Event Tracker');
        $table = new EventsTable($db);
        (new AdditionalTableActions($table, Auth::getInstance(), $this->url()))
            ->appendTo($this->actions());
        $table->handleSortUrl($this->url());
        $table->renderTo($this);
    }
}
