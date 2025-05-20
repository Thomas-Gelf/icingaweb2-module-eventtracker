<?php

namespace Icinga\Module\Eventtracker\Engine\Downtime;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use gipfl\Json\JsonSerialization;
use gipfl\Json\JsonString;
use gipfl\ZfDb\Adapter\Adapter;
use gipfl\ZfDbStore\DbStorableInterface;
use gipfl\ZfDbStore\NotFoundError;
use gipfl\ZfDbStore\ZfDbStore;
use Icinga\Module\Eventtracker\Data\SerializationHelper;
use Icinga\Module\Eventtracker\Time;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use RuntimeException;

class DowntimeRule implements JsonSerialization, DbStorableInterface
{
    use UuidObjectHelper;

    public const TABLE_NAME = 'downtime_rule';

    protected $tableName = self::TABLE_NAME;
    protected $keyProperty = 'uuid';

    protected $defaultProperties = [
        'uuid'                  => null,
        'time_definition'       => null,
        'filter_definition'     => null,
        'label'                 => null,
        'message'               => null,
        'timezone'              => null,
        'config_uuid'           => null,
        'host_list_uuid'        => null,
        'is_enabled'            => null,
        'is_recurring'          => null,
        'ts_not_before'         => null,
        'ts_not_after'          => null,
        'ts_triggered'          => null,
        'duration'              => null,
        'max_single_problem_duration' => null,
        'on_iteration_end_issue_status' => null,
    ];

    public static function loadByConfigUuid(string $uuid, Adapter $db): ?DowntimeRule
    {
        $dummy = new self();
        $table = $dummy->getTableName();
        if ($row = $db->fetchRow($db->select()->from($table)->where('config_uuid = ?', $uuid))) {
            $self = static::fromSerialization((object) $row);
            $self->setStored();
            return $self;
        }

        return null;
    }

    /**
     * @param ZfDbStore $store
     * @return static[]
     */
    public static function loadAll(ZfDbStore $store): array
    {
        $dummy = new self();
        $table = $dummy->getTableName();
        $objects = [];
        $db = $store->getDb();
        foreach ($db->fetchAll($db->select()->from($table)) as $row) {
            $object = static::fromSerialization((object) $row);
            $object->setStored();
            $objects[$row->uuid] = $object;
        }

        return $objects;
    }

    public function recalculateConfigUuid()
    {
        $new = $this->calculateMyConfigUuid();
        if ($this->get('config_uuid') !== $new) {
            $this->set('config_uuid', $new);
        }
    }

    public function getNotBefore(): ?DateTimeImmutable
    {
        return $this->getDateTimeProperty('ts_not_before');
    }

    public function getNotAfter(): ?DateTimeImmutable
    {
        return $this->getDateTimeProperty('ts_not_after');
    }

    public function getDuration(): ?DateInterval
    {
        if (null !== ($duration = $this->get('duration'))) {
            try {
                return new DateInterval('PT' . $duration . 'S');
            } catch (Exception $e) {
                // Should never happen
                throw new RuntimeException('Failed to assure valid interval, this is a bug', $e->getCode(), $e);
            }
        }

        return null;
    }

    protected function getDateTimeProperty(string $property): ?DateTimeImmutable
    {
        $ms = $this->get($property);
        if ($ms === null) {
            return null;
        }

        return Time::timestampMsToDateTime($ms, $this->getTimeZone());
    }

    protected function getTimeZone(): DateTimeZone
    {
        return new DateTimeZone($this->get('timezone'));
    }

    protected function calculateMyConfigUuid(): string
    {
        $properties = $this->getProperties();
        unset($properties['config_uuid']);
        $uuid = $this->get('uuid');
        if ($uuid === null) {
            throw new RuntimeException('Cannot recalculate config_uuid, have no UUID');
        }
        return Uuid::uuid5(
            Uuid::fromBytes($uuid),
            JsonString::encode(SerializationHelper::serializeProperties($properties))
        )->getBytes();
    }

    public function isEnabled(): bool
    {
        return $this->get('is_enabled') === 'y';
    }

    public static function loadWithConfigUuid(Adapter $db, UuidInterface $uuid): DowntimeRule
    {
        $select = $db->select()->from(self::TABLE_NAME)->where('config_uuid = ?', $uuid->getBytes());
        $result = $db->fetchAll($select);
        if (empty($result)) {
            throw new NotFoundError('Downtime rule not found by config UUID: ' . $uuid->toString());
        }

        if (count($result) > 1) {
            throw new NotFoundError(sprintf(
                'One Downtime rule expected by config UUID, got %s: %s',
                count($result),
                $uuid->toString()
            ));
        }
        $self = new DowntimeRule();
        $self->setStoredProperties((array) $result[0]);

        return $self;
    }

    /**
     * @deprecated
     */
    public function isRecurring(): bool
    {
        return $this->get('is_recurring') === 'y';
    }
}
