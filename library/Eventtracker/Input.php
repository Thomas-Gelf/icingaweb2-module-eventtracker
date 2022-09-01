<?php

namespace Icinga\Module\Eventtracker;

use gipfl\ZfDb\Adapter\Adapter as Db;
use gipfl\ZfDb\Select;
use Ramsey\Uuid\UuidInterface;

class Input
{
    use PropertyHelpers;

    protected static $tableName = 'input';

    protected $properties = [
        'uuid'           => null,
        'label'          => null,
        'implementation' => null,
        'settings'       => null
    ];

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

    protected static function create($properties): self
    {
        $sender = new static;
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
