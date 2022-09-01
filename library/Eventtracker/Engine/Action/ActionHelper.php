<?php

namespace Icinga\Module\Eventtracker\Engine\Action;

use Exception;
use gipfl\ZfDb\Adapter\Adapter;
use Icinga\Module\Eventtracker\ActionHistory;
use Icinga\Module\Eventtracker\Issue;
use Icinga\Module\Eventtracker\Web\Widget\IdoDetails;

class ActionHelper
{
    public static function processIssue(array $actions, Issue $issue, Adapter $db)
    {
        foreach ($actions as $action) {
            $filter = $action->getFilter();
            $details = new IdoDetails($issue, $db);
            $object = (object) $issue->getProperties();
            if ($details->hasHost()) {
                $host = $details->getHost();
                foreach ($host->customvars as $varName => $varValue) {
                    $object->{"host.vars.$varName"} = $varValue;
                }
            }

            if (
                $filter !== null
                && ! $action->getFilter()->matches($issue->getProperties())
            ) {
                continue;
            }

            $action->process($issue)->then(function ($message) use ($action, $issue, $db): void {
                ActionHistory::persist($action, $issue, true, (string) $message, $db);
            }, function (Exception $reason) use ($action, $issue, $db): void {
                ActionHistory::persist($action, $issue, false, (string) $reason, $db);
            });
        }
    }
}
