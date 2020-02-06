<?php

namespace Icinga\Module\Eventtracker\Hook;

use Icinga\Module\Eventtracker\Issue;
use Icinga\Module\Eventtracker\SetOfIssues;

abstract class EventActionsHook
{
    abstract public function getIssueActions(Issue $issue);

    /**
     * @param SetOfIssues $issues
     * @return array
     */
    public function getIssuesActions(SetOfIssues $issues)
    {
        return [];
    }
}
