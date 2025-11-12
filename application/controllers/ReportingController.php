<?php

namespace Icinga\Module\Eventtracker\Controllers;

use gipfl\IcingaWeb2\Widget\Tabs;
use Icinga\Authentication\Auth;
use Icinga\Module\Eventtracker\Reporting\HistorySummaries;
use Icinga\Module\Eventtracker\Web\Form\Reporting\AggregationTypeForm;
use Icinga\Module\Eventtracker\Web\Form\Reporting\ReportEndForm;
use Icinga\Module\Eventtracker\Web\Form\Reporting\ReportStartForm;
use Icinga\Module\Eventtracker\Web\Table\Reporting\HistorySummaryTable;
use Icinga\Module\Eventtracker\Web\Widget\AdditionalTableActions;
use Icinga\Web\Url;

class ReportingController extends Controller
{
    use RestApiMethods;

    protected AggregationTypeForm $formAggregationType;
    protected ReportStartForm $formStart;
    protected ReportEndForm $formEnd;

    public function init()
    {
        if (! $this->getRequest()->isApiRequest()) {
            if (! $this->Auth()->isAuthenticated()) {
                $this->redirectToLogin(Url::fromRequest());
            }
            $this->assertPermission('eventtracker/reporting');
        }
        $this->formAggregationType = (new AggregationTypeForm())->handleRequest($this->getServerRequest());
        $this->formStart = (new ReportStartForm())->handleRequest($this->getServerRequest());
        $this->formEnd = (new ReportEndForm())->handleRequest($this->getServerRequest());
    }

    protected $requiresAuthentication = false;

    public function historySummaryAction()
    {
        if ($this->getRequest()->isApiRequest()) {
            $this->runForApi(fn () => $this->sendHistorySummary());
            return;
        }
        if ($this->getParam('format') === 'json') {
            // Not using $this->optionallySendJsonForTable($table), as it is indexed differently
            $this->sendHistorySummary();
            return;
        }

        $this->addTitle($this->translate('Report: Issue History'));
        $this->reportingTabs()->activate('historySummary');
        $this->actions()->add([
            $this->formAggregationType,
            $this->formStart,
            $this->formEnd
        ]);
        $table = new HistorySummaryTable($this->db(), $this->requireReport());
        $table->getPaginator($this->url())->setItemsPerPage(100);
        (new AdditionalTableActions($table, Auth::getInstance(), $this->url()))
            ->appendTo($this->actions());
        $table->renderTo($this);
    }

    protected function reportingTabs(): Tabs
    {
        return $this->tabs()->add('historySummary', [
            'url' => 'eventtracker/report/history-summary',
            'label' => $this->translate('Issue History')
        ])/*->add('topHosts', [
            'url' => 'eventtracker/report/top-hosts',
            'label' => $this->translate('Top Hosts')
        ])->add('topProblemIdentifiers', [
            'url' => 'eventtracker/report/top-problem-identifiers',
            'label' => $this->translate('Top Problem Identifiers')
        ])*/;
    }

    protected function sendHistorySummary()
    {
        $report = $this->requireReport();
        $this->sendJsonResponse(['objects' => $report->fetchIndexed($report->select())]);
    }

    protected function requireReport(): HistorySummaries
    {
        return new HistorySummaries(
            $this->db(),
            $this->formAggregationType->getValue('aggregation'),
            $this->formStart->getDate(),
            $this->formEnd->getDate(),
        );
    }
}
