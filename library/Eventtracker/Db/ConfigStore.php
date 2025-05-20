<?php

namespace Icinga\Module\Eventtracker\Db;

use gipfl\Json\JsonDecodeException;
use gipfl\Json\JsonString;
use gipfl\ZfDb\Adapter\Adapter;
use Icinga\Module\Eventtracker\Engine\Action;
use Icinga\Module\Eventtracker\Engine\Action\ActionRegistry;
use Icinga\Module\Eventtracker\Engine\Bucket\BucketInterface;
use Icinga\Module\Eventtracker\Engine\Bucket\BucketRegistry;
use Icinga\Module\Eventtracker\Engine\Channel;
use Icinga\Module\Eventtracker\Engine\Downtime\DowntimeRunner;
use Icinga\Module\Eventtracker\Engine\Input;
use Icinga\Module\Eventtracker\Engine\Input\InputRegistry;
use Icinga\Module\Eventtracker\Engine\Registry;
use Icinga\Module\Eventtracker\Engine\Task;
use Icinga\Module\Eventtracker\Modifier\Settings;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use RuntimeException;

class ConfigStore
{
    protected $db;

    protected $serializedProperties = ['settings', 'rules', 'input_uuids'];

    /** @var LoggerInterface */
    protected $logger;

    public function __construct(Adapter $db, LoggerInterface $logger = null)
    {
        $this->db = $db;
        if ($logger === null) {
            $logger = new NullLogger();
        }
        $this->logger = $logger;
    }

    public function loadInputs($filter = [])
    {
        $inputs = [];
        foreach ($this->fetchObjects('input', $filter) as $row) {
            $row->uuid = Uuid::fromBytes($row->uuid);
            $inputs[$row->uuid->toString()] = $this->initializeTaskFromDbRow($row, new InputRegistry(), Input::class);
        }

        return $inputs;
    }

    protected function initializeTaskFromDbRow($row, Registry $registry, $contract): Task
    {
        /** @var class-string|Task $implementation */
        $implementation = $registry->getClassName($row->implementation);
        $interfaces = class_implements($implementation);
        if (isset($interfaces[$contract])) {
            $instance = new $implementation(
                Settings::fromSerialization($row->settings),
                $row->uuid,
                $row->label,
                $this->logger
            );
            if ($instance instanceof LoggerAwareInterface) {
                $instance->setLogger($this->logger);
            }

            return $instance;
        } else {
            throw new RuntimeException("Task ignored, $implementation is no valid implementation for $contract");
        }
    }

    /**
     * Hint: Daemon only!!
     * @param BucketInterface[] $buckets
     * @return Channel[]
     * @throws JsonDecodeException
     */
    public function loadChannels(DowntimeRunner $downtimeRunner, array $buckets): array
    {
        $channels = [];
        foreach ($this->fetchObjects('channel') as $row) {
            $uuid = Uuid::fromBytes($row->uuid);
            $bucketUuid = $row->bucket_uuid ? Uuid::fromBytes($row->bucket_uuid) : null;
            $channel = new Channel(Settings::fromSerialization([
                'rules'          => JsonString::decode($row->rules),
                'implementation' => $row->input_implementation,
                'inputs'         => $row->input_uuids,
            ]), $uuid, $row->label, $bucketUuid, $row->bucket_name, $buckets, $this->logger);
            $channel->setDowntimeRunner($downtimeRunner);
            $channels[$uuid->toString()] = $channel;
        }

        return $channels;
    }

    /**
     * @return BucketInterface[]
     */
    public function loadBuckets(): array
    {
        $buckets = [];
        foreach ($this->fetchObjects('bucket') as $row) {
            $row->uuid = Uuid::fromBytes($row->uuid);
            $bucket = $this->initializeTaskFromDbRow(
                $row,
                new BucketRegistry(),
                BucketInterface::class
            );
            assert($bucket instanceof BucketInterface);
            $buckets[$row->uuid->toString()] = $bucket;
        }

        return $buckets;
    }

    public function loadActions($filter = []): array
    {
        $actions = [];
        foreach ($this->fetchObjects('action', $filter) as $row) {
            $row->uuid = Uuid::fromBytes($row->uuid);
            /** @var Action $action */
            try {
                $action = $this->initializeTaskFromDbRow($row, new ActionRegistry(), Action::class);
                $actions[$row->uuid->toString()] = $action
                    ->setActionDescription($row->description)
                    ->setEnabled($row->enabled === 'y')
                    ->setFilter($row->filter);
            } catch (\Exception $e) {
                $this->logger->error('Failed to initialize ' . $row->label . ': ' . $e->getMessage());
                continue;
            }
        }

        return $actions;
    }

    public function fetchObject($table, UuidInterface $uuid)
    {
        $db = $this->db;
        $row = $db->fetchRow($db->select()->from($table)->where('uuid = ?', $uuid->getBytes()));
        $this->unserializeSerializedProperties($row);

        return $row;
    }

    protected function unserializeSerializedProperties($row)
    {
        foreach ($this->serializedProperties as $property) {
            if (isset($row->$property)) {
                $row->$property = JsonString::decode($row->$property);
            }
        }
    }

    protected function fetchObjects($table, $filter = [])
    {
        $db = $this->db;
        $query = "SELECT * FROM $table";
        if (! empty($filter)) {
            $query .= ' WHERE';
        }
        $filters = [];
        foreach ($filter as $key => $value) {
            $filters[] = $db->quoteInto(sprintf(' %s = ?', $db->quoteIdentifier($key)), $value);
        }
        $query .= implode(' AND ', $filters);
        $query .= ' ORDER BY label';
        $rows = $db->fetchAll($query);
        foreach ($rows as $row) {
            $this->unserializeSerializedProperties($row);
        }
        return $rows;
    }

    public function enumObjects($table)
    {
        $db = $this->db;
        $result = [];
        foreach ($db->fetchPairs("SELECT uuid, label FROM $table ORDER BY label") as $uuid => $label) {
            $result[Uuid::fromBytes($uuid)->toString()] = $label;
        }

        return $result;
    }

    public function deleteObject($table, UuidInterface $uuid)
    {
        return $this->db->delete($table, $this->quotedWhere($uuid));
    }

    /**
     * @param $table
     * @param $object
     * @return bool|UuidInterface
     * @throws \gipfl\ZfDb\Adapter\Exception\AdapterException
     * @throws \gipfl\ZfDb\Statement\Exception\StatementException
     */
    public function storeObject($table, $object)
    {
        $this->propertyArrayCleanup($object);
        $isUpdate = isset($object['uuid']);
        if ($isUpdate) {
            $uuid = Uuid::fromString($object['uuid']);
            unset($object['uuid']);
        } else {
            $uuid = Uuid::uuid4();
        }
        if ($isUpdate) {
            return $this->db->update($table, $object, $this->quotedWhere($uuid)) > 0;
        } else {
            $object['uuid'] = $uuid->getBytes();
            $this->db->insert($table, $object);
        }

        return $uuid;
    }

    /**
     * @deprecated
     * @return Adapter
     */
    public function getDb()
    {
        return $this->db;
    }

    protected function quotedWhere(UuidInterface $uuid)
    {
        return $this->db->quoteInto('uuid = ?', $uuid->getBytes());
    }

    protected function propertyArrayCleanup(&$array)
    {
        foreach ($this->serializedProperties as $property) {
            if (isset($array[$property])) {
                $array[$property] = JsonString::encode($array[$property]);
            }
        }
        foreach ($array as $key => &$value) {
            if (substr($key, -5) === '_uuid' && $value !== null && strlen($value) > 16) {
                $value = Uuid::fromString($value)->getBytes();
            }
        }
    }
}
