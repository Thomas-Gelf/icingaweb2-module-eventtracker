<?php

namespace Icinga\Module\Eventtracker\Scom;

use Icinga\Module\Eventtracker\Event;
use Icinga\Module\Eventtracker\EventReceiver;
use Icinga\Module\Eventtracker\Issue;
use Icinga\Module\Eventtracker\ObjectClassInventory;
use Icinga\Module\Eventtracker\SenderInventory;
use Zend_Db_Adapter_Abstract as DbAdapter;
use Zend_Db_Adapter_Pdo_Mssql as Mssql;

class ScomSync
{
    protected $db;

    protected $senderId;

    public function __construct(DbAdapter $db)
    {
        $this->db = $db;
    }

    /**
     * @param Mssql $db
     */
    public function syncFromDb(Mssql $db)
    {
        $query = file_get_contents(__DIR__ . '/scom-alerts.sql');
        $this->shipEvents($db->fetchAll($query));
    }

    /**
     * @param \stdClass[] $objects
     */
    public function syncFromPlainObjects($objects)
    {
        $this->shipEvents($objects);
    }

    protected function shipEvents($events)
    {
        $db = $this->db;
        $classes = new ObjectClassInventory($db);
        $senderId = $this->getSenderId();
        $factory = new ScomEventFactory($senderId, $classes);
        $receiver = new EventReceiver($db);

        $issuesFromScom = [];
        foreach ($events as $scomEvent) {
            $event = $factory->fromPlainObject($scomEvent);
            $issue = $receiver->processEvent($event);
            if ($issue) {
                $issuesFromScom[$event->get('sender_event_id')] = $issue->getUuid();
            }
        }

        foreach ($this->fetchExistingEventIds() as $eventId => $issueUuid) {
            if (! isset($issuesFromScom[$eventId])) {
                Issue::recoverUuid($issueUuid, $db);
            }
        }
    }

    protected function getSenderId()
    {
        if ($this->senderId === null) {
            $senders = new SenderInventory($this->db);
            $this->senderId = $senders->getSenderId('SCOM', 'new-scom');
        }

        return $this->senderId;
    }

    protected function fetchExistingEventIds()
    {
        $db = $this->db;
        $ids = $db->fetchPairs(
            $db->select()
                ->from('issue', ['sender_event_id', 'issue_uuid'])
                ->where('sender_id = ?', $this->getSenderId())
        );

        return $ids;
    }
}
