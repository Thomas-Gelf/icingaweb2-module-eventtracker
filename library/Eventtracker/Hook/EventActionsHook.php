<?php

namespace Icinga\Module\Eventtracker\Hook;

use Icinga\Module\Eventtracker\Issue;

abstract class EventActionsHook
{
    abstract public function getIssueActions(Issue $issue);
}
