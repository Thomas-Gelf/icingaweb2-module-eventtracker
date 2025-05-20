<?php

namespace Icinga\Module\Eventtracker\Engine\Action;

use gipfl\ZfDb\Adapter\Adapter;
use Icinga\Module\Eventtracker\ActionHistory;
use Icinga\Module\Eventtracker\Engine\Action;
use Icinga\Module\Eventtracker\Engine\EnrichmentHelper;
use Icinga\Module\Eventtracker\Issue;
use Psr\Log\LoggerInterface;
use React\EventLoop\Loop;
use React\Promise\ExtendedPromiseInterface;
use Throwable;
use function React\Promise\all;

class ActionHelper
{
    /** @var array<string, string>|null */
    protected static $problemHandling = null;

    public static function processIssue(
        array $actions,
        Issue $issue,
        Adapter $db,
        LoggerInterface $logger
    ): ExtendedPromiseInterface {
        $promises = [];
        $loggingSuffix =  ' for issue ' . $issue->getNiceUuid();
        /** @var Action $action */
        foreach ($actions as $action) {
            $filter = $action->getFilter();
            $object = EnrichmentHelper::enrichIssueForFilter($issue, $db);
            if ($filter !== null && ! $filter->matches($object)) {
                $logger->debug('Action filter ignores ' . $action->getName() . $loggingSuffix);
                continue;
            }

            $logger->debug('Triggering action ' . $action->getName() . $loggingSuffix);
            $promises[] = $action->process($issue)->then(function ($message) use ($action, $issue, $db): void {
                ActionHistory::persist($action, $issue, true, (string) $message, $db);
            }, function (Throwable $reason) use ($action, $issue, $db): void {
                ActionHistory::persist($action, $issue, false, (string) $reason, $db);
            });
        }

        return all($promises);
    }
}
