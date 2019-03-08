<?php

namespace Icinga\Module\Eventtracker\Hook;

use Icinga\Module\Eventtracker\Incident;

abstract class EventActionsHook
{
    abstract public function getIncidentActions(Incident $incident);
}
