<?php

namespace Icinga\Module\Eventtracker;

use Zend_Db_Adapter_Abstract as Db;

class IssueHistory
{
    const REASON_RECOVERY = 'recovery';
    const REASON_MANUAL = 'manual';
    const REASON_EXPIRATION = 'expiration';

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
            $activity->modifications = \json_decode($activity->modifications);
            $activities[] = $activity;
        }
        $properties['close_reason'] = $reason;
        if ($closedBy !== null) {
            $properties['closed_by'] = $closedBy;
        }
        $properties['activities'] = \json_encode($activities);

        $db->insert('issue_history', $properties);
    }
}
