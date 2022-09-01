<?php

namespace Icinga\Module\Eventtracker\Engine;

use gipfl\Json\JsonString;
use gipfl\ZfDb\Adapter\Adapter;
use Icinga\Module\Eventtracker\Issue;
use Icinga\Module\Eventtracker\Web\Widget\IdoDetails;
use Ramsey\Uuid\Uuid;

class EnrichmentHelper
{
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

    protected static function getPlainIssue(Issue $issue, $flat = false): object
    {
        $object = (object) $issue->getProperties();
        foreach (['issue_uuid', 'input_uuid'] as $key) {
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
