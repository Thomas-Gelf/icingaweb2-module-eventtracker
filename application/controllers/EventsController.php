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

        $table = new EventsTable($db);
        if ($this->params->get('view') === 'compact') {
            $table->setNoHeader();
            $table->search($this->params->get('q'));
            $table->handleSortUrl($this->url());
            $this->content()->add($table);
        } else {
            $this->addSingleTab('Events');
            $this->addTitle('Event Tracker');
            (new AdditionalTableActions($table, Auth::getInstance(), $this->url()))
                ->appendTo($this->actions());
            $table->handleSortUrl($this->url());
            $table->renderTo($this);
        }
    }
}
