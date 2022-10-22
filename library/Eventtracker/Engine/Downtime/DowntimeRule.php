<?php

namespace Icinga\Module\Eventtracker\Engine\Downtime;

use gipfl\Json\JsonSerialization;
use gipfl\Json\JsonString;
use gipfl\ZfDbStore\DbStorableInterface;
use gipfl\ZfDbStore\ZfDbStore;
use Ramsey\Uuid\Uuid;

class DowntimeRule implements JsonSerialization, DbStorableInterface
{
    use UuidObjectHelper;

    protected $tableName = 'downtime_rule';
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
        'next_calculated_uuid'  => null,
        'is_enabled'            => null,
        'is_recurring'          => null,
        'ts_not_before'         => null,
        'ts_not_after'          => null,
        'duration'              => null,
        'max_single_problem_duration' => null,
    ];

    protected $integers = [
        'duration',
        'max_single_problem_duration',
    ];

    public static function loadAll(ZfDbStore $store): array
    {
        $dummy = new self();
        $table = $dummy->getTableName();
        $objects = [];
        $db = $store->getDb();
        foreach ($db->fetchAll($db->select()->from($table)) as $row) {
            $objects[$row->uuid] = static::fromSerialization((object) $row);
        }

        return $objects;
    }

    public function recalculateConfigUuid()
    {
        $properties = $this->getProperties();
        unset($properties['config_uuid']);
        $uuid = $this->get('uuid');
        if ($uuid === null) {
            throw new \RuntimeException('Cannot recalculate config_uuid, have no UUID');
        }
        $current = $this->get('config_uuid');
        $new =  Uuid::uuid5(
            Uuid::fromBytes($uuid),
            JsonString::encode($this->serializeProperties($properties))
        )->getBytes();
        if ($current !== $new) {
            $this->set('config_uuid', $new);
        }
    }

    /**
     * @deprecated
     */
    public function isRecurring(): bool
    {
        return $this->get('is_recurring') === 'y';
    }
}
