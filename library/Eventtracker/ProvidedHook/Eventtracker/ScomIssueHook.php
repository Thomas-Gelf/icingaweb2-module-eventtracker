<?php

namespace Icinga\Module\Eventtracker\ProvidedHook\Eventtracker;

use Icinga\Application\Config;
use Icinga\Module\Eventtracker\ConfigHelper;
use Icinga\Module\Eventtracker\Hook\IssueHook;
use Icinga\Module\Eventtracker\Issue;
use Ramsey\Uuid\Uuid;
use React\ChildProcess\Process;
use React\EventLoop\Loop;

class ScomIssueHook extends IssueHook
{
    public function onUpdate(Issue $issue)
    {
        if ($this->isScomIssue($issue)) {
            if ($issue->hasModifiedProperty('ticket_ref')) {
                $this->updateTicketRef($issue);
            }
        }
    }

    protected function updateTicketRef(Issue $issue)
    {
        $this->eventuallyRun('cmd_ticket_ref', $issue);
    }

    public function onClose(Issue $issue)
    {
        if ($this->isScomIssue($issue)) {
            $this->eventuallyRun('cmd_close', $issue);
        }
    }

    protected function isScomIssue(Issue $issue)
    {
        $db = $this->getDb();
        $query = $db->select()
            ->from('sender', 'id')
            ->where('implementation = ?', 'new-scom');

        $id = $db->fetchOne($query);

        if ($id) {
            return (int) $issue->get('sender_id') === (int) $id;
        } else {
            return null;
        }
    }

    protected function eventuallyRun($cmdConfigName, Issue $issue)
    {
        $cmd = Config::module('eventtracker')->get('scom', $cmdConfigName);
        if ($cmd === null) {
            // Allow config section per Input, to have an issue hook there too
            $cmd = Config::module('eventtracker')->get('scom', Uuid::fromBytes($issue->get('input_uuid')));
        }
        if ($cmd === null) {
            return null;
        }
        $cmd = ConfigHelper::fillPlaceholders($cmd, $issue);
        $succeeded = null;
        Loop::futureTick(function () use ($issue, $cmd, &$succeeded) {
            $cmd = ConfigHelper::fillPlaceholders($cmd, $issue);
            $process = new Process($cmd);
            $process->start();
            $process->on('exit', function ($code, $term) use (&$succeeded) {
                $succeeded = ($term === null && $code === 0);
                Loop::stop();
            });
        });
        Loop::run();

        return $succeeded;
    }
}
