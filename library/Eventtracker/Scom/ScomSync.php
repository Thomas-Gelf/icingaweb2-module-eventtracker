<?php

namespace Icinga\Module\Eventtracker\Scom;

use gipfl\ZfDb\Adapter\Adapter as DbAdapter;
use gipfl\ZfDb\Adapter\Pdo\Mssql as Mssql;
use Icinga\Application\Config;
use Icinga\Module\Eventtracker\Daemon\Logger;
use Icinga\Module\Eventtracker\EventReceiver;
use Icinga\Module\Eventtracker\Issue;
use Icinga\Module\Eventtracker\ObjectClassInventory;
use Icinga\Module\Eventtracker\SenderInventory;
use InvalidArgumentException;
use RuntimeException;

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
        $filename = Config::module('eventtracker')->get('scom', 'query_file', 'scom-alerts.sql');
        if (substr($filename, 0, 1) !== '/') {
            $filename = __DIR__ . "/$filename";
        }
        if (substr($filename, -4) !== '.sql') {
            throw new InvalidArgumentException("Query file name must end with '.sql', got '$filename'");
        }
        $query = file_get_contents($filename);
        if ($query) {
            $this->shipEvents($db->fetchAll($query));
        } else {
            throw new RuntimeException("Failed to read query from '$filename'");
        }
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
        $cntIgnored = 0;
        $cntNew = 0;
        $cntRecovered  = 0;
        foreach ($events as $scomEvent) {
            $event = $factory->fromPlainObject($scomEvent);
            $issue = $receiver->processEvent($event);
            if ($issue) {
                if ($issue->hasBeenCreatedNow()) {
                    $cntNew++;
                }
                $issuesFromScom[$event->get('sender_event_id')] = $issue->getUuid();
            } else {
                $cntIgnored++;
            }
        }

        foreach ($this->fetchExistingEventIds() as $eventId => $issueUuid) {
            if (! isset($issuesFromScom[$eventId])) {
                Issue::recoverUuid($issueUuid, $db);
                $cntRecovered++;
            }
        }

        if ($cntRecovered + $cntNew === 0) {
            // Logger::debug('Got nothing new from SCOM');
        } else {
            Logger::info(\sprintf(
                'SCOM sync: %d new, %d recovered',
                $cntNew,
                $cntRecovered
            ));
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
