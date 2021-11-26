<?php

namespace Icinga\Module\Eventtracker\Clicommands;

use gipfl\ZfDb\Adapter\Adapter;
use gipfl\ZfDb\Adapter\Pdo\Mssql;
use Icinga\Module\Eventtracker\Check\PluginState;
use Icinga\Module\Eventtracker\DbFactory;
use Icinga\Module\Eventtracker\DbSeverity;
use Icinga\Module\Eventtracker\Scom\Scom;
use Icinga\Module\Eventtracker\Scom\ScomQuery;
use Icinga\Module\Eventtracker\Severity;

/**
 * Icinga Check Commands provided by the Eventtracker
 */
class CheckCommand extends Command
{
    /**
     * Run checks against the SCOM MSSQL DB
     */
    public function scomAction()
    {
        try {
            $issues = $this->fetchScomIssues(
                $this->requireMssqlResource($this->requireDbResourceName()),
                $this->requireParam('host'),
                $this->params->get('service')
            );
        } catch (\Exception $e) {
            $state = PluginState::unknown();
            $this->showWithState($e->getMessage(), $state);
            exit($state->getExitCode());
        }
        if (empty($issues)) {
            $state = PluginState::ok();
            $this->showWithState('No related SCOM alert has been found', $state);
            exit($state->getExitCode());
        }

        $criticals = [];
        $warnings = [];
        $state = new PluginState();
        foreach ($issues as $issue) {
            if ($issue->resolution_state === Scom::RESOLUTION_STATE_RESOLVED) {
                continue;
            }
            if ($issue->in_maintenance) {
                $state->raise(PluginState::STATE_WARNING);
                $warnings[] = $issue;
            } else {
                if ($issue->alert_severity === 'critical') {
                    $state->raise(PluginState::STATE_CRITICAL);
                    $criticals[] = $issue;
                } elseif ($issue->alert_severity === 'warning') {
                    $state->raise(PluginState::STATE_WARNING);
                    $warnings[] = $issue;
                }
            }
        }
        $issues = array_merge($criticals, $warnings);
        if (empty($issues)) {
            $this->showWithState('SCOM has related alerts, but no current issues', PluginState::ok());
        } elseif (\count($issues) === 1) {
            $this->showWithState(\reset($issues)->description, $state);
        } else {
            $this->showWithState(
                \reset($issues)->description
                . \sprintf("\n...and %d more alerts", \count($issues) - 1),
                $state
            );
        }
        exit($state->getExitCode());
    }

    /**
     * This checks whether there are issues for the given filter
     *
     * USAGE
     *
     * icingacli eventtracker check alert [--filter <val>[, --filter <val>]]
     *
     * VALID FILTER COLUMNS
     *
     *   - host_name
     *   - object_name
     *   - object_class
     *
     * EXAMPLE
     *
     * Any issues/alerts:
     *
     *     icingacli eventtracker check alert
     *
     * Issues/alerts for a specific Object Class
     *
     *     icingacli eventtracker check alert --object_class System.Computer
     */
    public function alertAction()
    {
        $db = DbFactory::db();
        $issues = $this->fetchIssues($db);
        $state = PluginState::ok();
        if (empty($issues)) {
            $this->showWithState('No related issues found', $state);
            exit($state->getExitCode());
        }
        $first = array_shift($issues);
        $state->raise(
            $first->severity === Severity::WARNING
                ? PluginState::STATE_WARNING
                : PluginState::STATE_CRITICAL
        );

        $message = $this->shorten($this->firstLine($first->message), 80);
        $cntRemaining = count($issues);
        if ($cntRemaining === 1) {
            $message .= ', and one more issue found';
        } elseif ($cntRemaining > 1) {
            $message .= ", and $cntRemaining more issues found";
        }
        $this->showWithState($message, $state);
        exit($state->getExitCode());
    }

    protected function shorten($string, $length)
    {
        if (strlen($string) < $length) {
            return $string;
        }

        return substr($string, 0, $length) . '...';
    }

    protected function firstLine($string)
    {
        return preg_split('/\r?\n/', $string)[0];
    }

    protected function fetchIssues(Adapter $db)
    {
        $query = $db->select()->from('issue')
            ->where('status = ?', 'open')
            ->where('severity >= ?', DbSeverity::WARNING)
            ->order('severity DESC')
            ->order('priority DESC')
            ->order('ts_first_event DESC');
        $validFilters = [
            'host_name',
            'object_class',
            'object_name'
        ];
        foreach ($validFilters as $filter) {
            if ($value = $this->params->shift($filter)) {
                $query->where("$filter = ?", $value);
            }
        }
        $remaining = $this->params->getParams();
        if (! empty($remaining)) {
            $this->fail('Unsupported filter: ' . implode(', ', array_keys($remaining)));
        }

        return $db->fetchAll($query);
    }

    protected function fetchScomIssues(Mssql $db, $host, $service = null)
    {
        $columns = ScomQuery::getDefaultColumns();
        $query = ScomQuery::prepareBaseQuery($db)
            ->columns($columns)
            // ->where('entity_name = ?', $host);
            ->where($columns['entity_name'] . $db->quoteInto(' = ?', $host));
        if ($service !== null) {
            // $query->where('alert_name = ?', $service);
            $query->where($columns['alert_name'] . $db->quoteInto(' = ?', $service));
        }

        return $db->fetchAll($query);
    }

    protected function requireDbResourceName()
    {
        $resource = $this->Config()->get('scom', 'db_resource');
        if ($resource === null) {
            $resource = $this->params->get('db-resource');
        }
        if ($resource === null) {
            $this->fail('Got neither a configured [scom] db_resource nor --db-resource');
        }

        return $resource;
    }

    protected function showWithState($message, PluginState $state)
    {
        \printf("[%s] %s\n", $state, \rtrim($message, "\n"));
    }

    /**
     * @param $name
     * @return mixed
     */
    protected function requireParam($name)
    {
        $value = $this->params->get('host');
        if ($value === null) {
            $this->fail("The --$name parameter is required");
        }

        return $value;
    }

    /**
     * @param $msg
     * @param PluginState|null $state
     * @return never-returns
     */
    public function fail($msg, PluginState $state = null)
    {
        if ($state === null) {
            $state = PluginState::unknown();
        }
        $this->showWithState($msg, PluginState::unknown());
        exit($state->getExitCode());
    }
}
