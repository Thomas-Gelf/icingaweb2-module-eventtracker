<?php

namespace Icinga\Module\Eventtracker\Controllers;

use Icinga\Authentication\Auth;
use Icinga\Module\Eventtracker\Db\EventSummaryBySeverity;
use Icinga\Module\Eventtracker\DbFactory;
use Icinga\Module\Eventtracker\Web\Table\IssueHistoryTable;
use Icinga\Module\Eventtracker\Web\Widget\AdditionalTableActions;
use Icinga\Module\Eventtracker\Web\Widget\SeverityFilter;
use Icinga\Module\Eventtracker\Web\Widget\ToggleSeverities;
use Icinga\Web\Widget\Tabextension\DashboardAction;
use ipl\Html\Html;

class IssueshistoryController extends Controller
{
    use IssuesFilterHelper;

    public function indexAction()
    {
        $this->setAutorefreshInterval(20);
        $db = DbFactory::db();

        $table = new IssueHistoryTable($db, $this->url());
        $this->applyFilters($table);
        if (! $this->url()->getParam('sort')) {
            $this->url()->setParam('sort', 'severity DESC');
        }
        $filters = Html::tag('ul', ['class' => 'nav'], [
            // Order & ensureAssembled matters!
            // temporarily disabled, should be configurable:
            // (new TogglePriorities($this->url()))->applyToQuery($table->getQuery())->ensureAssembled(),
            (new ToggleSeverities($this->url()))->applyToQuery($table->getQuery())->ensureAssembled(),
        ]);
        // $sevSummary = new EventSummaryBySeverity($table->getQuery());
        // $summary = new SeverityFilter($sevSummary->fetch($db), $this->url());

        if ($this->showCompact()) {
            $table->setNoHeader();
            $table->showCompact();
            $table->getQuery()->limit(50);
            $this->content()->add($table);
        } else {
            if (! $this->params->get('wide')) {
                $table->showCompact();
            }
            $this->addSingleTab('Issues');
            $this->setTitle('Event Tracker');
            $this->controls()->addTitle('Historic Issues');
            $this->actions()->add($filters);
            $this->actions()->add($this->createViewToggle());
            $table->getQuery()->limit(50);
            (new AdditionalTableActions($table, Auth::getInstance(), $this->url()))
                ->appendTo($this->actions());
            $this->eventuallySendJson($table);
            $table->renderTo($this);
        }

        if (! $this->showCompact()) {
            $this->tabs()->extend(new DashboardAction());
        }
    }
}
