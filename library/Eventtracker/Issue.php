<?php

namespace Icinga\Module\Eventtracker;

use gipfl\Json\JsonString;
use gipfl\ZfDb\Adapter\Adapter as Db;
use gipfl\ZfDb\Expr;
use Icinga\Application\Hook;
use Icinga\Authentication\Auth;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Eventtracker\Hook\IssueHook;
use Ramsey\Uuid\Uuid;

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
        'input_uuid'            => null,
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
     * @return string
     */
    public static function exists($uuid, Db $db)
    {
        $result = $db->fetchOne(
            $db->select()
                ->from(self::$tableName, 'COUNT(*)')
                ->where('issue_uuid = ?', $uuid)
        );

        return $result;
    }

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
     * @param $uuid
     * @param Db $db
     * @return Issue|null
     */
    public static function loadIfExists($uuid, Db $db)
    {
        $result = $db->fetchRow(
            $db->select()
                ->from(self::$tableName)
                ->where('issue_uuid = ?', $uuid)
        );

        if ($result) {
            return static::createStored($result);
        } else {
            return null;
        }
    }

    /**
     * @param $uuid
     * @param Db $db
     * @return Issue|null
     */
    public static function loadFromHistory($uuid, Db $db)
    {
        $result = $db->fetchRow(
            $db->select()
                ->from('issue_history')
                ->where('issue_uuid = ?', $uuid)
        );

        $activities = JsonString::decode($result->activities);
        $closeReason = $result->close_reason;
        $closedBy = $result->closed_by;
        unset($result->activities);
        unset($result->close_reason);
        unset($result->closed_by);
        $result->status = 'closed';

        if ($result) {
            $issue = new static();
            $issue->setProperties($result);
            $issue->set('status', 'closed');

            return $issue;
        } else {
            return null;
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

        // We need a better handling for those:
        if ($properties['acknowledge']) {
            $this->set('status', 'acknowledged');
        }
        unset($properties['acknowledge']);

        // We might want to handle clear, but what about hooks?
        unset($properties['clear']);

        if ($attributes === null) {
            $attributes = [];
        }
        unset($properties['event_timeout'], $properties['attributes']);
        $attributes = array_filter((array) $attributes, function ($key) {
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

    /**
     * @deprecated
     * @return string
     */
    public function getHexUuid()
    {
        return bin2hex($this->get('issue_uuid'));
    }

    public function getNiceUuid()
    {
        return Uuid::fromBytes($this->get('issue_uuid'))->toString();
    }

    protected function triggerHooks($action, Db $db)
    {
        /** @var IssueHook[] $handlers */
        $handlers = Hook::all('eventtracker/issue');
        foreach ($handlers as $handler) {
            $handler->setDb($db);
            $handler->$action($this);
        }
    }

    /**
     * @param Db $db
     * @return bool
     * @throws \gipfl\ZfDb\Adapter\Exception\AdapterException
     * @throws \gipfl\ZfDb\Statement\Exception\StatementException
     */
    public function storeToDb(Db $db)
    {
        $action = $this->detectEventualHookAction();
        if ($this->isNew()) {
            $result = $this->insertIntoDb($db);
        } else {
            // Will be fired in addition to more specific actions:
            $this->triggerHooks('onUpdate', $db);
            $result = $this->updateDb($db);
        }
        if ($action !== null) {
            $this->triggerHooks($action, $db);
        }
        $this->setStored();

        return $result;
    }

    /**
     * Could be externalized
     *
     * @return string|null
     */
    protected function detectEventualHookAction()
    {
        if ($this->isNew()) {
            return 'onCreate';
        } else {
            $modified = $this->getModifiedProperties();
            if (isset($modified['status'])) {
                $newStatus = $modified['status'];
                $oldStatus = $this->getStoredProperty('status');
                if ($oldStatus === 'closed') {
                    return 'onReOpen';
                } else {
                    switch ($newStatus) {
                        case 'closed':
                            return 'onClose';
                        case 'in_downtime':
                            return 'onDowntime';
                        case 'acknowledged':
                            return 'onAcknowledge';
                    }
                }
            }
        }

        // acknowledgement removed, downtime finished
        return null;
    }

    public function getAttributes()
    {
        if (empty($this->properties['attributes'])) {
            return (object) [];
        }

        $result = JsonString::decode($this->properties['attributes']);
        if (is_array($result) && empty($result)) {
            return (object) []; // Wrongly encoded
        }

        return $result;
    }

    public function getAttribute($name, $default = null)
    {
        $attrs = $this->getAttributes();
        if (\property_exists($attrs, $name)) {
            return $attrs->$name;
        }

        return $default;
    }

    public function setAttributes($attributes)
    {
        if (\is_string($attributes)) {
            $this->properties['attributes'] = $attributes;
        } else {
            $attributes = (array) $attributes;
            ksort($attributes);
            $this->properties['attributes'] = JsonString::encode($attributes);
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
     * @throws \gipfl\ZfDb\Adapter\Exception\AdapterException
     * @throws \gipfl\ZfDb\Statement\Exception\StatementException
     */
    protected function insertIntoDb(Db $db)
    {
        $now = static::now();
        $this->setProperties([
            'issue_uuid'       => Uuid::uuid4()->getBytes(),
            'cnt_events'       => 1,
            'status'           => 'open',
            'ts_first_event'   => $now,
            'ts_last_modified' => $now,
        ]);
        $properties = $this->getProperties();
        if ($properties['sender_event_id'] === null) {
            $properties['sender_event_id'] = '';
        }

        $db->insert(self::$tableName, $properties);

        return true;
    }

    /**
     * @param Db $db
     * @return bool
     * @throws \gipfl\ZfDb\Adapter\Exception\AdapterException
     * @throws \gipfl\ZfDb\Statement\Exception\StatementException
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
        $properties = [
            'cnt_events' => new Expr('cnt_events + 1'),
        ] + $this->getProperties();

        // Compat:
        if (array_key_exists('sender_event_id', $properties)) {
            if ($properties['sender_event_id'] === null) {
                $properties['sender_event_id'] = '';
            }
        } else {
            $properties['sender_event_id'] = '';
        }
        $db->update(self::$tableName, $properties, $where);
        unset($modifications['ts_expiration']);
        if (! empty($modifications)) {
            $db->insert('issue_activity', [
                'activity_uuid' => Uuid::uuid4()->getBytes(),
                'issue_uuid'    => $this->getUuid(),
                'ts_modified'   => $this::now(),
                'modifications' => Json::encode($modifications)
            ]);
        }

        return true;
    }

    public function close(Db $db, Auth $auth = null)
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

    public static function eventuallyRecover(Event $event, Db $db)
    {
        $issue = Issue::loadIfEventExists($event, $db);
        if ($issue) {
            return static::closeIssue($issue, $db, IssueHistory::REASON_RECOVERY);
        }

        return false;
    }

    public function recover(Event $event, Db $db)
    {
        return static::closeIssue($this, $db, IssueHistory::REASON_RECOVERY);
    }

    public static function recoverUuid($uuid, Db $db)
    {
        return static::closeIssue(Issue::load($uuid, $db), $db, IssueHistory::REASON_RECOVERY);
    }

    public static function expireUuid($uuid, Db $db)
    {
        return static::closeIssue(Issue::load($uuid, $db), $db, IssueHistory::REASON_EXPIRATION);
    }

    public static function closeIssue(Issue $issue, Db $db, $reason, $closedBy = null)
    {
        // TODO: Update? Log warning? Merge actions?
        //       -> This happens only when closing the issue formerly failed badly
        if (! IssueHistory::exists($issue->getUuid(), $db)) {
            IssueHistory::persist($issue, $db, $reason, $closedBy);
            $issue->set('status', 'closed');
            $action = $issue->detectEventualHookAction();
            if ($action !== null) {
                $issue->triggerHooks($action, $db);
            }
        }

        $db->delete(static::$tableName, $db->quoteInto('issue_uuid = ?', $issue->getUuid()));

        return true;
    }

    protected static function now()
    {
        return (int) floor(microtime(true) * 1000);
    }
}
