<?php

namespace Icinga\Module\Eventtracker\Controllers;

use gipfl\Json\JsonString;
use Icinga\Authentication\Auth;
use Icinga\Module\Eventtracker\Auth\RestrictionHelper;
use Icinga\Module\Eventtracker\Db\EventSummaryBySeverity;
use Icinga\Module\Eventtracker\Issue;
use Icinga\Module\Eventtracker\Web\Table\IssuesTable;
use Icinga\Module\Eventtracker\Web\Widget\AdditionalTableActions;
use Icinga\Module\Eventtracker\Web\Widget\SeverityFilter;
use Icinga\Module\Eventtracker\Web\Widget\ToggleSeverities;
use Icinga\Module\Eventtracker\Web\Widget\ToggleStatus;
use Icinga\Web\Widget\Tabextension\DashboardAction;
use ipl\Html\Html;
use Ramsey\Uuid\Uuid;

class IssuesController extends Controller
{
    use IssuesFilterHelper;
    use RestApiMethods;

    protected $requiresAuthentication = false;

    public function init()
    {
        parent::init();
        if ($this->getRequest()->isApiRequest()) {
            return;
        } else {
            $this->assertPermission('module/eventtracker');
        }
    }

    public function indexAction()
    {
        if ($this->getRequest()->isApiRequest()) {
            $this->runForApi(function () {
                $this->listForApi();
            });
            return;
        }

        $this->setAutorefreshInterval(20);
        $db = $this->db();

        $table = new IssuesTable($db, $this->url());
        RestrictionHelper::applyInputFilters($table->getQuery(), $this->Auth());
        $this->applyFilters($table);
        if (! $this->url()->getParam('sort')) {
            $this->url()->setParam('sort', 'severity DESC');
        }
        $filters = Html::tag('ul', ['class' => 'nav'], [
            // Order & ensureAssembled matters!
            // temporarily disabled, should be configurable:
            // (new TogglePriorities($this->url()))->applyToQuery($table->getQuery())->ensureAssembled(),
            (new ToggleSeverities($this->url()))->applyToQuery($table->getQuery())->ensureAssembled(),
            (new ToggleStatus($this->url()))->applyToQuery($table->getQuery())->ensureAssembled(),
        ]);
        $sevSummary = new EventSummaryBySeverity($table->getQuery());
        $summary = new SeverityFilter($sevSummary->fetch($db), $this->url());

        if ($this->showCompact()) {
            $table->setNoHeader();
            $table->showCompact();
            $table->getQuery()->limit(1000);
            $this->content()->add($table);
        } else {
            if (! $this->params->get('wide')) {
                $table->showCompact();
            }
            $this->addSingleTab('Issues');
            $this->setTitle('Event Tracker');
            if ($this->hasAppliedFilters()) {
                $this->controls()->addTitle('Filtered Issues', $summary);
            } else {
                $this->controls()->addTitle('Current Issues', $summary);
            }
            $this->actions()->add($filters);
            $this->actions()->add($this->createViewToggle());
            $table->getQuery()->limit(1000);
            (new AdditionalTableActions($table, Auth::getInstance(), $this->url()))
                ->appendTo($this->actions());
            $this->eventuallySendJson($table);
            $table->renderTo($this);
        }

        if (! $this->showCompact()) {
            $this->tabs()->extend(new DashboardAction());
        }
    }

    protected function listForApi()
    {
        if (! $this->checkBearerToken('issues/fetch')) { // Hint: does not return on error
            return;
        }

        $query = $this->db()->select()->from(['i' => 'issue'], []);
        self::applyColumnAndFilterParams($query, $this->url(), $this->listValidIssueProperties());
        $result = $this->db()->fetchAll($query);
        foreach ($result as $row) {
            self::fixIssueResultRow($row);
        }
        $this->sendJsonResponse($result);
    }

    protected function listValidIssueProperties(): array
    {
        return array_keys((new Issue())->getDefaultProperties());
    }

    protected static function fixIssueResultRow($row)
    {
        if (isset($row->issue_uuid)) {
            $row->issue_uuid = Uuid::fromBytes($row->issue_uuid)->toString();
        }
        if (isset($row->input_uuid)) {
            $row->input_uuid = Uuid::fromBytes($row->input_uuid)->toString();
        }
        if (isset($row->downtime_rule_uuid)) {
            $row->downtime_rule_uuid = Uuid::fromBytes($row->downtime_rule_uuid)->toString();
        }
        if (isset($row->downtime_config_uuid)) {
            $row->downtime_config_uuid = Uuid::fromBytes($row->downtime_config_uuid)->toString();
        }
        if (isset($row->attributes)) {
            $row->attributes = JsonString::decode($row->attributes);
        }
        unset($row->sender_event_checksum);
    }
}
