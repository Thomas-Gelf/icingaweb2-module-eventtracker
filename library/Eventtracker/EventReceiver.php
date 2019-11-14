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
     * @return Issue|null
     * @throws \Zend_Db_Adapter_Exception
     */
    public function processEvent(Event $event)
    {
        $issue = Issue::loadIfEventExists($event, $this->db);
        if ($event->isProblem()) {
            if ($issue) {
                $issue->setPropertiesFromEvent($event);
            } else {
                $issue = Issue::create($event, $this->db);
            }
            $issue->storeToDb($this->db);
        } elseif ($issue) {
            $issue->recover($event, $this->db);

            return null;
        }

        return $issue;
    }
}
