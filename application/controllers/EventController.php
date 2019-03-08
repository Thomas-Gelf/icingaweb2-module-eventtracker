<?php

namespace Icinga\Module\Eventtracker\Controllers;

use gipfl\IcingaWeb2\CompatController;
use Icinga\Module\Eventtracker\DbFactory;
use Icinga\Module\Eventtracker\Hook\EventActionsHook;
use Icinga\Module\Eventtracker\Incident;
use Icinga\Module\Eventtracker\Uuid;
use Icinga\Module\Eventtracker\Web\Table\EventDetailsTable;
use Icinga\Web\Hook;
use ipl\Html\Html;

class EventController extends CompatController
{
    /**
     * @throws \Icinga\Exception\MissingParameterException
     * @throws \Icinga\Exception\NotFoundError
     */
    public function indexAction()
    {
        $db = DbFactory::db();
        $this->addSingleTab('Event');
        $uuid = $this->params->getRequired('uuid');
        $incident = Incident::load(Uuid::toBinary($uuid), $db);
        $this->addTitle(sprintf(
            '%s (%s)',
            $incident->get('object_name'),
            $incident->get('host_name')
        ));
        $this->addHookedActions($incident);
        $this->content()->add([
            Html::tag('div', ['class' => 'output border-' . $incident->get('severity')], [
                Html::tag('h2', $incident->get('severity')),
                Html::tag('pre', ['class' => 'output'], $incident->get('message')),
            ]),
            new EventDetailsTable($incident)
        ]);
    }

    protected function addHookedActions(Incident $incident)
    {
        $actions = $this->actions();
        /** @var EventActionsHook $impl */
        foreach (Hook::all('eventtracker/EventActions') as $impl) {
            $actions->add($impl->getIncidentActions($incident));
        }
    }
}
