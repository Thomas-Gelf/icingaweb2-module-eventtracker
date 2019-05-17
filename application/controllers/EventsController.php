<?php

namespace Icinga\Module\Eventtracker\Controllers;

use gipfl\IcingaWeb2\CompatController;
use Icinga\Authentication\Auth;
use Icinga\Module\Eventtracker\DbFactory;
use Icinga\Module\Eventtracker\Uuid;
use Icinga\Module\Eventtracker\Web\Table\EventsTable;
use Icinga\Module\Eventtracker\Web\Widget\AdditionalTableActions;

class EventsController extends CompatController
{
    public function indexAction()
    {
        $this->setAutorefreshInterval(5);
        $db = DbFactory::db();

        $table = new EventsTable($db);

        if ($this->getRequest()->isApiRequest()) {
            $table->search($this->params->get('q'));
            $table->handleSortUrl($this->url());
            $result = $table->fetch();
            foreach ($result as & $row) {
                $row->incident_uuid = Uuid::toHex($row->incident_uuid);
            }
            $flags = JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE;
            echo json_encode($result, $flags);
            exit;
        }
        if (! $this->url()->getParam('sort')) {
            $this->url()->setParam('sort', 'severity DESC');
        }
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
