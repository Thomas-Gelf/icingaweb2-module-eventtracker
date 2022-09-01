<?php

namespace Icinga\Module\Eventtracker;

use gipfl\ZfDb\Adapter\Adapter as Db;
use gipfl\ZfDb\Select;

class Sender
{
    use PropertyHelpers;

    protected static $tableName = 'sender';

    protected $properties = [
        'id'             => null,
        'sender_name'    => null,
        'implementation' => null
    ];

    public static function byId($id, Db $db): ?self
    {
        $row = $db->fetchRow(
            static::select($db)
                ->where('s.id = ?', $id)
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
            ->from(['s' => static::$tableName]);
    }
}
