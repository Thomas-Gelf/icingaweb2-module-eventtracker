<?php

namespace Icinga\Module\Eventtracker;

use gipfl\ZfDb\Adapter\Adapter as Db;
use Icinga\Module\Eventtracker\Engine\Action;
use Ramsey\Uuid\Uuid;

class ActionHistory
{
    protected static $tableName = 'action_history';

    public static function persist(Action $action, Issue $issue, bool $success, string $message, Db $db)
    {
        $db->insert(self::$tableName, [
            'uuid'        => Uuid::uuid4()->getBytes(),
            'action_uuid' => $action->getUuid()->getBytes(),
            'issue_uuid'  => $issue->getUuid(),
            'ts_done'     => Time::unixMilli(),
            'success'     => $success ? 'y' : 'n',
            'message'     => $message
        ]);
    }
}
