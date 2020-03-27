<?php

namespace Icinga\Module\Eventtracker\Clicommands;

use Icinga\Module\Eventtracker\Scom\Scom;
use Icinga\Module\Eventtracker\Scom\ScomQuery;

class CheckCommand extends Command
{
    protected $worstState = 0;

    /**
     * Run checks against the SCOM MSSQL DB
     */
    public function scomAction()
    {
        $resource = $this->Config()->get('scom', 'db_resource');
        if ($resource === null) {
            $resource = $this->params->get('db-resource');
        }
        if ($resource === null) {
            $this->fail('Got neither a configured [scom] db_resource nor --db-resource');
        }
        $host = $this->params->get('host');
        if ($host === null) {
            $this->fail('The --host parameter is required');
        }
        $service = $this->params->get('service');
        $db = $this->requireMssqlResource($resource);
        $query = ScomQuery::prepareBaseQuery($db)
            ->columns(ScomQuery::getDefaultColumns())
            ->where('object_name = ?', $host);
        if ($service !== null) {
            $query->where('alert_name = ?', $service);
        }

        try {
            $issues = $db->fetchAll($query);
        } catch (\Exception $e) {
            $this->showWithState($e->getMessage(), 3);
            exit(3);
        }
        if (empty($issues)) {
            $this->showWithState('No related SCOM alert has been found');
            exit(0);
        }

        $criticals = [];
        $warnings = [];
        foreach ($issues as $issue) {
            if ($issue->resolution_state === Scom::RESOLUTION_STATE_RESOLVED) {
                continue;
            }
            if ($issue->in_maintenance) {
                $this->raiseWorstState(1);
                $warnings[] = $issue;
            } else {
                if ($issue->alert_severity === 'critical') {
                    $this->raiseWorstState(2);
                    $criticals[] = $issue;
                } elseif ($issue->alert_severity === 'warning') {
                    $this->raiseWorstState(1);
                    $warnings[] = $issue;
                }
            }
        }
        $issues = array_merge($criticals, $warnings);
        if (empty($issues)) {
            $this->showWithState('SCOM has related alerts, but no current issues');
        } elseif (\count($issues) === 1) {
            $this->showWithState(\reset($issues)->description);
        } else {
            $this->showWithState(
                \reset($issues)->description
                . \sprintf("\n...and %d more alerts", \count($issues) - 1)
            );
        }
        exit($this->worstState);
    }

    protected function showWithState($message, $state = null)
    {
        \printf("[%s] %s\n", $this->getStateName($state), \rtrim($message, "\n"));
    }

    protected function getStateName($state = null)
    {
        if ($state === null) {
            $state = $this->worstState;
        }

        $states = [
            'OK',
            'WARNING',
            'CRITICAL',
            'UNKNOWN'
        ];

        return $this->screen->colorize($states[$state]);
    }

    protected function raiseWorstState($state)
    {
        if ($state > $this->worstState) {
            $this->worstState = $state;
            // TODO: Unknown VS Critical - however, there is no Unknown right now
        }
    }

    public function fail($message)
    {
        $this->showWithState($message, 3);
        exit(3);
    }
}
