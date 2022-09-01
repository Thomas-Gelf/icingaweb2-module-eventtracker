<?php

namespace Icinga\Module\Eventtracker\Engine\Action;

use gipfl\ZfDb\Adapter\Adapter;
use Icinga\Module\Eventtracker\ActionHistory;
use Icinga\Module\Eventtracker\Engine\Action;
use Icinga\Module\Eventtracker\Issue;
use React\Promise\ExtendedPromiseInterface;
use Throwable;
use function React\Promise\all;

class ActionHelper
{
    public static function processIssue(array $actions, Issue $issue, Adapter $db): ExtendedPromiseInterface
    {
        $promises = [];

        /** @var Action $action */
        foreach ($actions as $action) {
            $filter = $action->getFilter();
            $object = EnrichmentHelper::enrichIssueForFilter($issue, $db);
            if ($filter !== null && ! $filter->matches($object)) {
                continue;
            }

            $promises[] = $action->process($issue)->then(function ($message) use ($action, $issue, $db): void {
                ActionHistory::persist($action, $issue, true, (string) $message, $db);
            }, function (Throwable $reason) use ($action, $issue, $db): void {
                ActionHistory::persist($action, $issue, false, (string) $reason, $db);
            });
        }

        return all($promises);
    }
}
