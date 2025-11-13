<?php

namespace Icinga\Module\Eventtracker\Controllers;

use gipfl\IcingaWeb2\Widget\Tabs;
use Icinga\Authentication\Auth;
use Icinga\Module\Eventtracker\Reporting\HistorySummaries;
use Icinga\Module\Eventtracker\Reporting\TopTalkers;
use Icinga\Module\Eventtracker\Web\Form\Reporting\AggregationSubjectForm;
use Icinga\Module\Eventtracker\Web\Form\Reporting\AggregationTypeForm;
use Icinga\Module\Eventtracker\Web\Form\Reporting\ReportEndForm;
use Icinga\Module\Eventtracker\Web\Form\Reporting\ReportStartForm;
use Icinga\Module\Eventtracker\Web\Table\BaseTable;
use Icinga\Module\Eventtracker\Web\Table\Reporting\HistorySummaryTable;
use Icinga\Module\Eventtracker\Web\Table\Reporting\TopTalkersTable;
use Icinga\Module\Eventtracker\Web\Widget\AdditionalTableActions;
use Icinga\Web\Url;

class ReportingController extends Controller
{
    use RestApiMethods;

    protected AggregationTypeForm $formAggregationType;
    protected AggregationSubjectForm $formAggregationSubject;
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
        $this->formAggregationSubject = (new AggregationSubjectForm())->handleRequest($this->getServerRequest());
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
        $this->showTable(new HistorySummaryTable($this->db(), $this->requireHistorySummaryReport()));
    }

    public function topTalkersAction()
    {
        if ($this->getRequest()->isApiRequest()) {
            $this->runForApi(fn () => $this->sendTopTalkers());
            return;
        }
        if ($this->getParam('format') === 'json') {
            // Not using $this->optionallySendJsonForTable($table), as it is indexed differently
            $this->sendTopTalkers();
            return;
        }

        $this->addTitle($this->translate('Report: Top Talkers'));
        $this->reportingTabs()->activate('topTalkers');
        $this->actions()->add([
            $this->formAggregationSubject,
            $this->formStart,
            $this->formEnd
        ]);
        $this->showTable(new TopTalkersTable($this->db(), $this->requireTopTalkersReport()));
    }

    protected function showTable(BaseTable $table)
    {
        $table->getPaginator($this->url())->setItemsPerPage(100);
        (new AdditionalTableActions($table, Auth::getInstance(), $this->url()))
            ->appendTo($this->actions());
        $table->renderTo($this);
    }

    protected function sendHistorySummary()
    {
        $report = $this->requireHistorySummaryReport();
        $this->sendJsonResponse(['objects' => $report->fetchIndexed($report->select())]);
    }

    protected function sendTopTalkers()
    {
        $report = $this->requireTopTalkersReport();
        $this->sendJsonResponse(['objects' => $report->fetchIndexed($report->select())]);
    }

    protected function requireHistorySummaryReport(): HistorySummaries
    {
        return new HistorySummaries(
            $this->db(),
            $this->formAggregationType->getValue('aggregation'),
            $this->formStart->getDate(),
            $this->formEnd->getDate(),
        );
    }

    protected function requireTopTalkersReport(): TopTalkers
    {
        return new TopTalkers(
            $this->db(),
            $this->formAggregationSubject->getValue('aggregation'),
            $this->formStart->getDate(),
            $this->formEnd->getDate(),
        );
    }

    protected function reportingTabs(): Tabs
    {
        return $this->tabs()->add('historySummary', [
            'url' => 'eventtracker/report/history-summary',
            'label' => $this->translate('Issue History')
        ])->add('topTalkers', [
            'url' => 'eventtracker/reporting/top-talkers',
            'label' => $this->translate('Top Talkers')
        ]);
    }
}
