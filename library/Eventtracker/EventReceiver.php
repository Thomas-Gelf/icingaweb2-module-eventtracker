<?php

namespace Icinga\Module\Eventtracker;

use Zend_Db_Adapter_Abstract as Db;

class EventReceiver
{
    protected $db;

    public function __construct(Db $db)
    {
        $this->db = $db;
    }

    /**
     * @param Event $event
     * @return Incident|null
     * @throws \Zend_Db_Adapter_Exception
     */
    public function processEvent(Event $event)
    {
        $incident = Incident::loadIfEventExists($event, $this->db);
        if ($event->isProblem()) {
            if ($incident) {
                $incident->setPropertiesFromEvent($event);
            } else {
                $incident = Incident::create($event, $this->db);
            }
        } else {
            $incident->resolve($event);
        }

        $incident->storeToDb($this->db);

        return $incident;
    }
}
