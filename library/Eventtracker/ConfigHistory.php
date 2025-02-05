<?php

namespace Icinga\Module\Eventtracker;

use gipfl\Json\JsonString;
use gipfl\ZfDbStore\DbStorableInterface;
use gipfl\ZfDb\Adapter\Adapter as Db;

class ConfigHistory
{
    public const TABLE_NAME = 'config_history';
    /** @var Db */
    protected $db;

    public function __construct(Db $db)
    {
        $this->db = $db;
    }

    public function trackChanges(
        string $objectType,
        ?DbStorableInterface $old,
        ?DbStorableInterface $new,
        string $author
    ) {
        if ($old === null) {
            $action = 'create';
            $latest = $new;
        } elseif ($new === null) {
            $action = 'delete';
            $latest = $old;
        } else {
            $action = 'modify';
            $latest = $new;
        }

        $this->db->insert(self::TABLE_NAME, [
            'ts_modification'  => Time::unixMilli(),
            'action'           => $action,
            // TODO: We might want to ask for a more specific interface -> config_uuid, label
            'object_uuid'      => $latest->get('uuid'),
            'config_uuid'      => $latest->get('config_uuid'),
            'object_type'      => $objectType,
            'label'            => $latest->get('label'),
            'properties_old'   => JsonString::encode($old),
            'properties_new'   => JsonString::encode($new),
            'author'           => $author,
        ]);
    }
}
