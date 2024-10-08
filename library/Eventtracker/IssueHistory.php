<?php

namespace Icinga\Module\Eventtracker;

use gipfl\Json\JsonString;
use gipfl\ZfDb\Adapter\Adapter as Db;

class IssueHistory
{
    const REASON_RECOVERY = 'recovery';
    const REASON_MANUAL = 'manual';
    const REASON_EXPIRATION = 'expiration';

    protected static $tableName = 'issue_history';

    /**
     * @param $uuid
     * @param Db $db
     * @return string
     */
    public static function exists($uuid, Db $db)
    {
        $result = $db->fetchOne(
            $db->select()
                ->from(self::$tableName, 'COUNT(*)')
                ->where('issue_uuid = ?', $uuid)
        );

        return $result;
    }

    public static function getReasonIfClosed($uuid, Db $db): ?\stdClass
    {
        $result = $db->fetchRow(
            $db->select()
                ->from(self::$tableName, ['close_reason', 'closed_by'])
                ->where('issue_uuid = ?', $uuid)
        );
        if (empty($result)) {
            return null;
        }

        return $result;
    }

    public static function persist(Issue $issue, Db $db, $reason, $closedBy = null)
    {
        $blacklist = ['status'];
        $properties = $issue->getProperties();
        foreach ($blacklist as $key) {
            unset($properties[$key]);
        }
        $activities = [];

        $query = $db->select()
            ->from(['i' => 'issue_activity'], [
                'ts'            => 'i.ts_modified',
                'modifications' => 'i.modifications'
            ])
            ->where('issue_uuid = ?', $issue->getUuid())
            ->order('ts_modified DESC');
        foreach ($db->fetchAll($query) as $activity) {
            $activity->modifications = JsonString::decode($activity->modifications);
            $activities[] = $activity;
        }
        $properties['close_reason'] = $reason;
        if ($closedBy !== null) {
            $properties['closed_by'] = $closedBy;
        }
        $properties['activities'] = JsonString::encode($activities);

        $db->insert(self::$tableName, $properties);
    }
}
