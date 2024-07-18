<?php

namespace Icinga\Module\Eventtracker;

use gipfl\Json\JsonSerialization;
use gipfl\Json\JsonString;
use gipfl\ZfDb\Adapter\Adapter as Db;
use Icinga\Application\Hook;
use Icinga\Authentication\Auth;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Eventtracker\Db\DbUtil;
use Icinga\Module\Eventtracker\Engine\Downtime\UuidObjectHelper;
use Icinga\Module\Eventtracker\Hook\IssueHook;
use Ramsey\Uuid\Uuid;

class Issue implements JsonSerialization
{
    use UuidObjectHelper {
        UuidObjectHelper::set as uuidObjectHelperSet;
        UuidObjectHelper::get as uuidObjectHelperGet;
    }

    const TABLE_NAME = 'issue';

    protected static $tableName = self::TABLE_NAME;

    /** @var FrozenMemoryFile[] */
    protected $files = [];

    protected $defaultProperties = [
        'issue_uuid' => null,
        'sender_event_checksum' => null,
        'status' => null,
        'severity' => null,
        'priority' => null,
        'host_name' => null,
        'object_name' => null,
        'object_class' => null,
        'problem_identifier' => null,
        'input_uuid' => null,
        'sender_id' => null,
        'sender_event_id' => null,
        'message' => null,
        'attributes' => null,
        'owner' => null,
        'ticket_ref' => null,
        'cnt_events' => null,
        'ts_first_event' => null,
        'ts_last_modified' => null,
        'ts_expiration' => null,
    ];

    protected $objectClass;

    protected $createdNow = false;

    /**
     * @param $uuid
     * @param Db $db
     * @return bool
     */
    public static function exists($uuid, Db $db): bool
    {
        return (int) $db->fetchOne(
            $db->select()
                ->from(self::$tableName, 'COUNT(*)')
                ->where('issue_uuid = ?', $uuid)
        ) > 0;
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

    public static function loadIfExists(string $uuid, Db $db): ?Issue
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

    public static function loadFromHistory(string $uuid, Db $db): ?Issue
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
            return static::create((array)$result);
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

    protected function getNonDbProperties(): array
    {
        if ($this->hasBeenCreatedNow()) {
            return ['has_been_created_now' => 'y'];
        }

        return [];
    }

    protected static function createStored($result)
    {
        $issue = static::create((array)$result);
        $issue->setStored();

        return $issue;
    }

    public static function createFromEvent(Event $event): Issue
    {
        $issue = Issue::create();
        $issue->setPropertiesFromEvent($event);
        $issue->createdNow = true;

        return $issue;
    }

    public function setPropertiesFromEvent(Event $event)
    {
        $properties = $event->getProperties();
        $timeout = $properties['event_timeout'];
        $attributes = $properties['attributes'];
        $eventUuid = $properties['uuid'];
        unset($properties['uuid']);

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

        // TODO: This filter is related to msend only, and can be removed, once
        //       msend module v0.3.0 is no longer supported.
        $attributes = array_filter((array)$attributes, function ($key) {
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
        if (!$this->isNew()) {
            unset($properties['priority']);
        }
        if ($timeout !== null) {
            $properties['ts_expiration'] = Time::unixMilli() + $timeout * 1000;
        }
        $properties['sender_event_checksum'] = $event->getChecksum();

        // Workaround for ghost changes... not so cool
        if ($properties['sender_event_id'] === null) {
            if ($this->get('sender_event_id') === '') {
                unset($properties['sender_event_id']);
            } else {
                $properties['sender_event_id'] = '';
            }
        }

        $this->setProperties($properties);

        $this->files = $event->getFiles();
    }

    public static function fromSerialization($any): self
    {
        if (isset($any->has_been_created_now)) {
            $hasBeenCreatedNow = $any->has_been_created_now;
            unset($any->has_been_created_now);
        } else {
            $hasBeenCreatedNow = null;
        }
        $self = static::create((array) $any);
        if ($hasBeenCreatedNow !== null) {
            $self->createdNow = $hasBeenCreatedNow;
        }

        return $self;
    }

    public function get($property, $default = null)
    {
        if ($property === 'has_been_created_now') {
            return $this->createdNow;
        }

        return $this->uuidObjectHelperGet($property, $default);
    }

    public function set($property, $value)
    {
        if ($property === 'has_been_created_now' && $value === true) {
            $this->createdNow = true;
            return;
        }
        $this->reallySet($property, $this->normalizeValue($property, $value));
    }

    public function hasBeenCreatedNow()
    {
        return $this->createdNow;
    }

    public function isNew(): bool
    {
        return $this->getStoredRepeatCount() === 0;
    }

    public function isClosed(): bool
    {
        return $this->get('status') === 'closed';
    }

    public function getStoredRepeatCount(): int
    {
        return (int) $this->get('cnt_events');
    }

    public function getUuid(): ?string
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

    public function getNiceUuid(): string
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
    public function storeToDb(Db $db): bool
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
     */
    protected function detectEventualHookAction(): ?string
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

    public function getStoredProperty($property)
    {
        $props = $this->getStoredProperties();
        return $props[$property];
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
        $this->set('owner', $owner);
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
        $uuid = Uuid::uuid4();
        $now = Time::unixMilli();
        $this->setProperties([
            'issue_uuid'       => $uuid->getBytes(),
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

        $files = [];
        foreach ($this->files as $file) {
            if (! File::exists($file, $db)) {
                File::persist($file, $db);
            }

            // Deduplication based on content and filename.
            $key = sprintf('%s!%s', bin2hex($file->getChecksum()), $file->getName());
            if (isset($files[$key])) {
                continue;
            }

            IssueFile::persist($uuid, $file, $db);

            $files[$key] = true;
        }

        return true;
    }

    /**
     * @param Db $db
     * @return bool
     * @throws \gipfl\ZfDb\Adapter\Exception\AdapterException
     * @throws \gipfl\ZfDb\Statement\Exception\StatementException
     */
    protected function updateDb(Db $db): bool
    {
        if (! $this->isModified()) {
            return false;
        }

        $modifications = $this->getModifiedProperties();
        foreach ($modifications as $key => $modification) {
            $modifications[$key] = [$this->getStoredProperty($key), $modification];
        }

        $properties = $this->getProperties();
        $eventRelatedProperties = $modifications;
        unset($eventRelatedProperties['owner']);
        unset($eventRelatedProperties['status']);
        unset($eventRelatedProperties['ticket_ref']);
        if (count($eventRelatedProperties) > 0) {
            $this->set('cnt_events', $this->get('cnt_events') + 1); // might be wrong, but safes a DB roundtrip
        }
        $this->set('ts_last_modified', Time::unixMilli());

        // Compat:
        if (array_key_exists('sender_event_id', $properties)) {
            if ($properties['sender_event_id'] === null) {
                $this->set('sender_event_id', '');
            }
        } else {
            $this->set('sender_event_id', '');
        }
        $where = $db->quoteInto('issue_uuid = ?', $this->getUuid());

        $activities = $db->fetchCol(
            $db->select()->from('issue_activity', 'activity_uuid')->where($where)->order('ts_modified')
        );
        // Keeping not more than 20 activities. Cannot delegate this to the DB, as DELETE with LIMIT is not
        // replication-safe in MySQL, even when used in a deterministic way with ORDER (bug #42851)
        $deleteUuids = array_slice($activities, 9, -10);
        // Chunked, to avoid issues with extra-long queries. This should only have an effect in environments with
        // lots of activities from older versions. After completed once per issue, there should never be more than
        // one chunk
        foreach (array_chunk($deleteUuids, 100) as $uuids) {
            $db->delete('issue_activity', $db->quoteInto('activity_uuid IN (?)', DbUtil::quoteBinary($uuids, $db)));
        }
        $db->update(self::$tableName, $this->getModifiedProperties(), $where);
        unset($modifications['ts_expiration']);
        if (! empty($modifications)) {
            $db->insert('issue_activity', [
                'activity_uuid' => Uuid::uuid4()->getBytes(),
                'issue_uuid'    => $this->getUuid(),
                'ts_modified'   => Time::unixMilli(),
                'modifications' => JsonString::encode($modifications)
            ]);
        }

        return true;
    }

    public function close(Db $db, Auth $auth = null): bool
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

    public static function eventuallyRecover(Event $event, Db $db): bool
    {
        $issue = Issue::loadIfEventExists($event, $db);
        if ($issue) {
            return static::closeIssue($issue, $db, IssueHistory::REASON_RECOVERY);
        }

        return false;
    }

    public function recover(Event $event, Db $db): bool
    {
        return static::closeIssue($this, $db, IssueHistory::REASON_RECOVERY);
    }

    public static function recoverUuid($uuid, Db $db): bool
    {
        return static::closeIssue(Issue::load($uuid, $db), $db, IssueHistory::REASON_RECOVERY);
    }

    public static function expireUuid($uuid, Db $db): bool
    {
        return static::closeIssue(Issue::load($uuid, $db), $db, IssueHistory::REASON_EXPIRATION);
    }

    public static function closeIssue(Issue $issue, Db $db, $reason, $closedBy = null): bool
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
}
