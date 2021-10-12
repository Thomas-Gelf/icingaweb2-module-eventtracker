<?php

namespace Icinga\Module\Eventtracker\Db;

use gipfl\ZfDb\Adapter\Adapter;
use Icinga\Module\Eventtracker\Data\Json;
use Icinga\Module\Eventtracker\Engine\Channel;
use Icinga\Module\Eventtracker\Engine\Input;
use Icinga\Module\Eventtracker\Modifier\Settings;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use RuntimeException;

class ConfigStore
{
    protected $db;

    protected $serializedProperties = ['settings', 'rules', 'input_uuids'];

    public function __construct(Adapter $db)
    {
        $this->db = $db;
    }

    public function loadInputs()
    {
        $inputs = [];
        foreach ($this->fetchObjects('input') as $row) {
            $implementation = $row->implementation;
            $interfaces = class_implements($implementation);
            if (isset($interfaces[Input::class])) {
                $uuid = Uuid::fromBytes($row->uuid);
                $inputs[$uuid->toString()] = new $implementation(
                    Settings::fromSerialization($row->settings),
                    $uuid,
                    $row->name
                );
            } else {
                throw new RuntimeException("Input ignored, $implementation is no valid implementation\n");
            }
        }

        return $inputs;
    }

    public function loadChannels()
    {
        $channels = [];
        foreach ($this->fetchObjects('channel') as $row) {
            $uuid = Uuid::fromBytes($row->uuid);
            $channels[$uuid->toString()] = new Channel(Settings::fromSerialization([
                'rules'          => $row->rules,
                'implementation' => $row->input_implementation,
                'inputs'         => $row->input_uuids,
            ]), $uuid, $row->name);
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

    protected function fetchObjects($table)
    {
        $db = $this->db;
        $rows = $db->fetchAll("SELECT * FROM $table ORDER BY label");
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
