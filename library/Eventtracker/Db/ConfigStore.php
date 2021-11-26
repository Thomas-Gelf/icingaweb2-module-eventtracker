<?php

namespace Icinga\Module\Eventtracker\Db;

use gipfl\ZfDb\Adapter\Adapter;
use Icinga\Module\Eventtracker\Data\Json;
use Icinga\Module\Eventtracker\Engine\Channel;
use Icinga\Module\Eventtracker\Engine\Input;
use Icinga\Module\Eventtracker\Engine\Input\InputRegistry;
use Icinga\Module\Eventtracker\Modifier\Settings;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use RuntimeException;

class ConfigStore
{
    protected $db;

    /** @var InputRegistry */
    protected $registry;

    protected $serializedProperties = ['settings', 'rules', 'input_uuids'];

    /** @var LoggerInterface */
    protected $logger;

    public function __construct(Adapter $db, LoggerInterface $logger = null)
    {
        $this->db = $db;
        $this->registry = new InputRegistry();
        $this->logger = $logger;
    }

    public function loadInputs($filter = [])
    {
        $inputs = [];
        foreach ($this->fetchObjects('input', $filter) as $row) {
            $row->uuid = Uuid::fromBytes($row->uuid);
            $inputs[$row->uuid->toString()] = $this->initializeInputFromDbRow($row);
        }

        return $inputs;
    }

    protected function initializeInputFromDbRow($row)
    {
        $implementation = $this->registry->getClassName($row->implementation);
        $interfaces = class_implements($implementation);
        if (isset($interfaces[Input::class])) {
            return new $implementation(
                Settings::fromSerialization($row->settings),
                $row->uuid,
                $row->label,
                $this->logger
            );
        } else {
            throw new RuntimeException("Input ignored, $implementation is no valid implementation\n");
        }
    }

    /**
     * @return Channel[]
     * @throws \Icinga\Module\Eventtracker\Data\JsonEncodeException
     */
    public function loadChannels()
    {
        $channels = [];
        foreach ($this->fetchObjects('channel') as $row) {
            $uuid = Uuid::fromBytes($row->uuid);
            $channels[$uuid->toString()] = new Channel(Settings::fromSerialization([
                'rules'          => Json::decode($row->rules),
                'implementation' => $row->input_implementation,
                'inputs'         => $row->input_uuids,
            ]), $uuid, $row->label, $this->logger);
        }

        return $channels;
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
                $row->$property = Json::decode($row->$property);
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
        foreach ($filter as $key => $value) {
            $query .= $db->quoteInto(sprintf(' %s = ?', $db->quoteIdentifier($key)), $value);
        }
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

    protected function quotedWhere(UuidInterface $uuid)
    {
        return $this->db->quoteInto('uuid = ?', $uuid->getBytes());
    }

    protected function propertyArrayCleanup(&$array)
    {
        foreach ($this->serializedProperties as $property) {
            if (isset($array[$property])) {
                $array[$property] = Json::encode($array[$property]);
            }
        }
    }
}
