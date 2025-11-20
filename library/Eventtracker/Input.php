<?php

namespace Icinga\Module\Eventtracker;

use gipfl\ZfDb\Adapter\Adapter as Db;
use gipfl\ZfDb\Select;
use Ramsey\Uuid\UuidInterface;

class Input
{
    use PropertyHelpers;

    protected static string $tableName = 'input';

    protected $properties = [
        'uuid'           => null,
        'label'          => null,
        'implementation' => null,
        'settings'       => null
    ];

    public function getUuid(): UuidInterface
    {
        return \Ramsey\Uuid\Uuid::fromBytes($this->get('uuid'));
    }

    public static function byId(UuidInterface $id, Db $db): ?self
    {
        $row = $db->fetchRow(
            static::select($db)
                ->where('i.uuid = ?', $id->getBytes())
        );
        if ($row === false) {
            return null;
        }

        return static::create($row);
    }

    /**
     * Hint: required by PropertyHelpers
     */
    public function isNew(): bool
    {
        return false;
    }

    protected static function create($properties): self
    {
        $sender = new Input();
        $sender->setProperties($properties);
        $sender->setStored();

        return $sender;
    }

    protected static function select(Db $db): Select
    {
        return $db->select()
            ->from(['i' => static::$tableName]);
    }
}
