<?php

namespace Icinga\Module\Eventtracker;

use Icinga\Authentication\Auth;
use Icinga\Exception\NotFoundError;
use Zend_Db_Adapter_Abstract as Db;
use Zend_Db_Expr as DbExpr;

class Issue
{
    use PropertyHelpers;

    protected static $tableName = 'issue';

    protected $properties = [
        'issue_uuid'            => null,
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
        'attributes'            => null,
        'owner'                 => null,
        'ticket_ref'            => null,
        'cnt_events'            => null,
        'ts_first_event'        => null,
        'ts_last_modified'      => null,
        'ts_expiration'         => null,
    ];

    protected $objectClass;

    protected $createdNow = false;

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

    public static function loadBySenderEventId($id, $senderId, Db $db)
    {
        $result = $db->fetchRow(
            $db->select()
                ->from(self::$tableName)
                ->where('sender_event_id = ?', $id)
                ->where('sender_id = ?', $senderId)
        );

        if ($result) {
            return static::createStored($result);
        } else {
            throw new NotFoundError('There is no such issue');
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
        $issue->createdNow = true;
        $issue->setPropertiesFromEvent($event);

        return $issue;
    }

    public function setPropertiesFromEvent(Event $event)
    {
        $properties = $event->getProperties();
        $timeout = $properties['event_timeout'];
        $attributes = $properties['attributes'];
        if ($attributes === null) {
            $attributes = [];
        }
        unset($properties['event_timeout'], $properties['attributes']);
        $attributes = array_filter($attributes, function ($key) {
            if ($key === 'severity' || $key === 'msg') {
                return false;
            }
            if (substr($key, 0, 3) === 'mc_') {
                return false;
            }

            return true;
        }, ARRAY_FILTER_USE_KEY);
        $this->setAttributes($attributes);

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

    public function hasBeenCreatedNow()
    {
        return $this->createdNow;
    }

    public function isNew()
    {
        return $this->getStoredRepeatCount() === 0;
    }

    public function isClosed()
    {
        return $this->get('status') === 'closed';
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

    public function getNiceUuid()
    {
        return Uuid::toHex($this->get('issue_uuid'));
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

    public function getAttributes()
    {
        if (empty($this->properties['attributes'])) {
            return (object) [];
        } else {
            return \json_decode($this->properties['attributes']);
        }
    }

    public function setAttributes($attributes)
    {
        if (\is_string($attributes)) {
            $this->properties['attributes'] = $attributes;
        } else {
            $this->properties['attributes'] = \json_encode($attributes);
        }
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
            'issue_uuid'       => Uuid::generate(),
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
        unset($modifications['ts_expiration']);
        if (! empty($modifications)) {
            $db->insert('issue_activity', [
                'activity_uuid' => Uuid::generate(),
                'issue_uuid'    => $this->getUuid(),
                'ts_modified'   => $this::now(),
                'modifications' => \json_encode($modifications)
            ]);
        }

        return true;
    }

    public function close(\Zend_Db_Adapter_Abstract $db, Auth $auth = null)
    {
        if ($auth === null) {
            $auth = Auth::getInstance();
        }

        return static::closeIssue(
            $this,
            $db,
            IssueHistory::REASON_MANUAL,
            $auth->getUser()->getUsername()
        );
    }

    public static function eventuallyRecover(Event $event, \Zend_Db_Adapter_Abstract $db)
    {
        $issue = Issue::loadIfEventExists($event, $db);
        if ($issue) {
            return static::closeIssue($issue, $db, IssueHistory::REASON_RECOVERY);
        } else {
            return false;
        }
    }

    public function recover(Event $event, \Zend_Db_Adapter_Abstract $db)
    {
        return static::closeIssue($this, $db, IssueHistory::REASON_RECOVERY);
    }

    public static function recoverUuid($uuid, \Zend_Db_Adapter_Abstract $db)
    {
        return static::closeIssue(Issue::load($uuid, $db), $db, IssueHistory::REASON_RECOVERY);
    }

    public static function expireUuid($uuid, \Zend_Db_Adapter_Abstract $db)
    {
        return static::closeIssue(Issue::load($uuid, $db), $db, IssueHistory::REASON_EXPIRATION);
    }

    public static function closeIssue(Issue $issue, \Zend_Db_Adapter_Abstract $db, $reason, $closedBy = null)
    {
        IssueHistory::persist($issue, $db, $reason, $closedBy);
        $db->delete(static::$tableName, $db->quoteInto('issue_uuid = ?', $issue->getUuid()));
        // TODO: delete from issue, store to issue_history
        return true;
    }

    protected static function now()
    {
        return (int) floor(microtime(true) * 1000);
    }
}
