<?php

namespace Icinga\Module\Eventtracker\Engine;

use gipfl\Json\JsonString;
use gipfl\ZfDb\Adapter\Adapter;
use Icinga\Module\Eventtracker\Issue;
use Icinga\Module\Eventtracker\Web\Widget\IdoDetails;
use Ramsey\Uuid\Uuid;

class EnrichmentHelper
{
    /** @var array<string, string>|null */
    protected static $problemHandling = null;

    public static function forgetProblemHandling()
    {
        self::$problemHandling = null;
    }

    protected static function loadProblemHandling(Adapter $db): void
    {
        self::$problemHandling = $db->fetchPairs(
            $db->select()
                ->from('problem_handling', ['label', 'instruction_url'])
                ->where('trigger_actions IS NULL OR trigger_actions = ?', 'n')
                ->where('trigger_actions != ?', 'y')
                ->where('enabled IS NULL OR enabled = ?', 'y')
        );
    }

    protected static function isSkippedByProblemHandling(Issue $issue, Adapter $db): bool
    {
        $problemIdentifier = $issue->get('problem_identifier');
        if (self::$problemHandling === null) {
            self::loadProblemHandling($db);
        }
        if (! isset(self::$problemHandling[$problemIdentifier])) {
            return false;
        }
    }

    public static function enrichIssue(Issue $issue, Adapter $db): object
    {
        $details = new IdoDetails($issue, $db);
        $object = self::getPlainIssue($issue);
        if ($details->hasHost()) {
            $host = $details->getHost();
            $vars = $host->customvars;
            if (! empty($vars)) {
                $object->host = (object) [
                    'vars' => (object) []
                ];
            }
            foreach ($vars as $varName => $varValue) {
                $object->host->vars->$varName = $varValue;
            }
        }

        return $object;
    }

    public static function enrichIssueForFilter(Issue $issue, Adapter $db): object
    {
        $details = new IdoDetails($issue, $db);
        $object = self::getPlainIssue($issue, true);
        if ($details->hasHost()) {
            $host = $details->getHost();
            foreach ($host->customvars as $varName => $varValue) {
                $object->{"host.vars.$varName"} = $varValue;
            }
        }

        return $object;
    }

    public static function getPlainIssue(Issue $issue, $flat = false): object
    {
        $object = (object) $issue->getProperties();
        foreach (['issue_uuid', 'input_uuid', 'downtime_config_uuid', 'downtime_rule_uuid'] as $key) {
            if ($object->$key !== null) {
                $object->$key = Uuid::fromBytes($object->$key)->toString();
            }
        }
        foreach (['sender_event_checksum'] as $key) {
            if ($object->$key !== null) {
                $object->$key = bin2hex($object->$key);
            }
        }
        if ($object->attributes) {
            if ($flat) {
                foreach (JsonString::decode($object->attributes) as $key => $value) {
                    $object->{"attributes.$key"} = $value;
                }
                unset($object->attributes);
            } else {
                $object->attributes = JsonString::decode($object->attributes);
            }
        }

        return $object;
    }
}
