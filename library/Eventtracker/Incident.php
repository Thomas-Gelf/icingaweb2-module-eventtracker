<?php

namespace Icinga\Module\Eventtracker;

use Icinga\Exception\NotFoundError;
use Zend_Db_Adapter_Abstract as Db;
use Zend_Db_Expr as DbExpr;

class Incident
{
    use PropertyHelpers;

    protected static $tableName = 'incident';

    protected $properties = [
        'incident_uuid'         => null,
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
        'cnt_events'            => null,
        'ts_first_event'        => null,
        'ts_last_modified'      => null,
        'ts_expiration'         => null,
    ];

    protected $objectClass;

    /**
     * @param $uuid
     * @param Db $db
     * @return Incident
     * @throws NotFoundError
     */
    public static function load($uuid, Db $db)
    {
        $result = $db->fetchRow(
            $db->select()
                ->from(self::$tableName)
                ->where('incident_uuid = ?', $uuid)
        );

        if ($result) {
            $incident = new static();
            $incident->setProperties($result);

            return $incident;
        } else {
            throw new NotFoundError('There is no such incident');
        }
    }

    /**
     * @param Event $event
     * @param Db $db
     * @return Incident|null
     */
    public static function loadIfEventExists(Event $event, Db $db)
    {
        $result = $db->fetchRow(
            $db->select()
                ->from(self::$tableName)
                ->where('sender_event_checksum = ?', $event->getChecksum())
        );

        if ($result) {
            $incident = new static();
            $incident->setProperties($result);

            return $incident;
        } else {
            return null;
        }
    }

    public static function create(Event $event, Db $db)
    {
        $incident = new Incident();
        $incident->setPropertiesFromEvent($event);

        return $incident;
    }

    public static function resolveIfExists(Event $event, Db $db)
    {
        if ($incident = Incident::loadIfEventExists($event, $db)) {
            $incident->resolve($event);
        }
    }

    public function setPropertiesFromEvent(Event $event)
    {
        $properties = $event->getProperties();
        $timeout = $properties['event_timeout'];
        unset($properties['event_timeout']);
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
        return $this->get('incident_uuid');
    }

    public function getHexUuid()
    {
        return bin2hex($this->get('incident_uuid'));
    }

    /**
     * @param Db $db
     * @throws \Zend_Db_Adapter_Exception
     */
    public function storeToDb(Db $db)
    {
        if ($this->isNew()) {
            $this->insertIntoDb($db);
        } else {
            $this->updateDb($db);
        }
    }

    /**
     * @param Db $db
     * @throws \Zend_Db_Adapter_Exception
     */
    protected function insertIntoDb(Db $db)
    {
        $now = static::now();
        $this->setProperties([
            'incident_uuid'    => Uuid::generate(),
            'cnt_events'       => 1,
            'status'           => 'open',
            'ts_first_event'   => $now,
            'ts_last_modified' => $now,
        ]);
        $db->insert(self::$tableName, $this->getProperties());
    }

    /**
     * @param Db $db
     * @throws \Zend_Db_Adapter_Exception
     */
    protected function updateDb(Db $db)
    {
        $this->setProperties([
            'cnt_events'       => $this->get('cnt_events') + 1, // might be wrong, but safes a DB roundtrip
            'ts_last_modified' => static::now(),
        ]);
        $where = $db->quoteInto('incident_uuid = ?', $this->getUuid());
        $db->update(self::$tableName, [
            'cnt_events' => new DbExpr('cnt_events + 1'),
        ] + $this->getProperties(), $where);
    }

    public static function resolve(Event $event)
    {
        // TODO: delete from incident, store to incident_history
    }

    protected static function now()
    {
        return (int) floor(microtime(true) * 1000);
    }
}
