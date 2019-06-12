<?php

namespace Icinga\Module\Eventtracker;

use Icinga\Exception\NotFoundError;
use Zend_Db_Adapter_Abstract as Db;
use Zend_Db_Expr as DbExpr;

class Issue
{
    use PropertyHelpers;

    protected static $tableName = 'issue';

    protected $properties = [
        'issue_uuid'         => null,
        'sender_event_checksum' => null,
        'status'                => null,
        'severity'              => null,
        'priority'              => null,
        'host_name'             => null,
        'object_name'           => null,
        'object_class'          => null,
        'sender_id'             => null,
        'sender_event_id'       => null,
        'message'               => null,
        'owner'                 => null,
        'ticket_ref'            => null,
        'cnt_events'            => null,
        'ts_first_event'        => null,
        'ts_last_modified'      => null,
        'ts_expiration'         => null,
    ];

    protected $objectClass;

    /**
     * @param $uuid
     * @param Db $db
     * @return Issue
     * @throws NotFoundError
     */
    public static function load($uuid, Db $db)
    {
        $result = $db->fetchRow(
            $db->select()
                ->from(self::$tableName)
                ->where('issue_uuid = ?', $uuid)
        );

        if ($result) {
            return static::createStored($result);
        } else {
            throw new NotFoundError('There is no such issue');
        }
    }

    /**
     * @param Event $event
     * @param Db $db
     * @return Issue|null
     */
    public static function loadIfEventExists(Event $event, Db $db)
    {
        $result = $db->fetchRow(
            $db->select()
                ->from(self::$tableName)
                ->where('sender_event_checksum = ?', $event->getChecksum())
        );

        if ($result) {
            return static::createStored($result);
        } else {
            return null;
        }
    }

    protected static function createStored($result)
    {
        $issue = new static();
        $issue->setProperties($result);
        $issue->setStored();

        return $issue;
    }

    public static function create(Event $event, Db $db)
    {
        $issue = new Issue();
        $issue->setPropertiesFromEvent($event);

        return $issue;
    }

    public static function resolveIfExists(Event $event, Db $db)
    {
        if ($issue = Issue::loadIfEventExists($event, $db)) {
            $issue->resolve($event);
        }
    }

    public function setPropertiesFromEvent(Event $event)
    {
        $properties = $event->getProperties();
        $timeout = $properties['event_timeout'];
        unset($properties['event_timeout']);

        // Priority can be customized, source will not be allowed to change it
        // We might however check whether we want to allow this for issues with
        // "unmodified" priority
        if (! $this->isNew()) {
            unset($properties['priority']);
        }
        if ($timeout !== null) {
            $properties['ts_expiration'] = static::now() + $timeout * 1000;
        }
        $properties['sender_event_checksum'] = $event->getChecksum();
        $this->setProperties($properties);

        return $this;
    }

    public function isNew()
    {
        return $this->getStoredRepeatCount() === 0;
    }

    public function getStoredRepeatCount()
    {
        return (int) $this->get('cnt_events');
    }

    public function getUuid()
    {
        return $this->get('issue_uuid');
    }

    public function getHexUuid()
    {
        return bin2hex($this->get('issue_uuid'));
    }

    /**
     * @param Db $db
     * @return bool
     * @throws \Zend_Db_Adapter_Exception
     */
    public function storeToDb(Db $db)
    {
        if ($this->isNew()) {
            $result = $this->insertIntoDb($db);
        } else {
            $result = $this->updateDb($db);
        }
        $this->setStored();

        return $result;
    }

    public function setOwner($owner)
    {
        $this->properties['owner'] = $owner;
        $this->fixOpenAck();
    }

    public function setTicketRef($ticketRef)
    {
        $this->properties['ticket_ref'] = $ticketRef;
        $this->fixOpenAck();
    }

    public function raisePriority()
    {
        $this->set('priority', Priority::raise($this->get('priority')));
    }

    public function lowerPriority()
    {
        $this->set('priority', Priority::lower($this->get('priority')));
    }

    protected function fixOpenAck()
    {
        $status = $this->get('status');
        if ($this->get('owner') === null && $this->get('ticket_ref') === null) {
            if ($status !== 'in_downtime' && $status !== 'closed') {
                $this->set('status', 'open');
            }
        } else {
            if ($status === 'open') {
                $this->set('status', 'acknowledged');
            }
        }
    }

    /**
     * @param Db $db
     * @return bool
     * @throws \Zend_Db_Adapter_Exception
     */
    protected function insertIntoDb(Db $db)
    {
        $now = static::now();
        $this->setProperties([
            'issue_uuid'    => Uuid::generate(),
            'cnt_events'       => 1,
            'status'           => 'open',
            'ts_first_event'   => $now,
            'ts_last_modified' => $now,
        ]);
        $db->insert(self::$tableName, $this->getProperties());

        return true;
    }

    /**
     * @param Db $db
     * @return bool
     * @throws \Zend_Db_Adapter_Exception
     */
    protected function updateDb(Db $db)
    {
        if (! $this->hasChanged()) {
            return false;
        }

        $modifications = $this->getModifications();
        $this->setProperties([
            'cnt_events'       => $this->get('cnt_events') + 1, // might be wrong, but safes a DB roundtrip
            'ts_last_modified' => static::now(),
        ]);
        $where = $db->quoteInto('issue_uuid = ?', $this->getUuid());
        $db->update(self::$tableName, [
            'cnt_events' => new DbExpr('cnt_events + 1'),
        ] + $this->getProperties(), $where);
        $db->insert('issue_activity', [
            'activity_uuid' => Uuid::generate(),
            'issue_uuid' => $this->getUuid(),
            'ts_modified'   => $this::now(),
            'modifications' => json_encode($modifications)
        ]);

        return true;
    }

    public static function resolve(Event $event)
    {
        // TODO: delete from issue, store to issue_history
    }

    protected static function now()
    {
        return (int) floor(microtime(true) * 1000);
    }
}
