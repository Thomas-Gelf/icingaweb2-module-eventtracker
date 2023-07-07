<?php

namespace Icinga\Module\Eventtracker\Controllers;

use Icinga\Authentication\Auth;
use Icinga\Module\Eventtracker\Web\Table\ActionHistoryTable;
use Icinga\Module\Eventtracker\Web\Table\IssueHistoryTable;
use Icinga\Module\Eventtracker\Web\Widget\AdditionalTableActions;

class HistoryController extends Controller
{
    use IssuesFilterHelper;

    public function actionsAction()
    {
        $this->addTitle('Action / Notification History');
        $this->historyTabs()->activate('actions');
        $this->setAutorefreshInterval(20);
        $db = $this->db();
        $table = new ActionHistoryTable($db, $this->url());
        if (! $this->url()->getParam('sort')) {
            $this->url()->setParam('sort', 'ts_done DESC');
        }
        $table->getQuery()->limit(50);
        (new AdditionalTableActions($table, Auth::getInstance(), $this->url()))
            ->appendTo($this->actions());
        $this->eventuallySendJson($table);
        $table->renderTo($this);
    }

    public function issuesAction()
    {
        $this->addTitle('Historic Issues');
        $this->historyTabs()->activate('issues');
        $this->setAutorefreshInterval(20);
        $db = $this->db();
        $table = new IssueHistoryTable($db, $this->url());
        if (! $this->url()->getParam('sort')) {
            $this->url()->setParam('sort', 'severity DESC');
        }
        $table->getQuery()->limit(50);
        (new AdditionalTableActions($table, Auth::getInstance(), $this->url()))
            ->appendTo($this->actions());
        $this->eventuallySendJson($table);
        $table->renderTo($this);
    }

    protected function historyTabs()
    {
        return $this->tabs()->add('issues', [
            'label' => $this->translate('Issue History'),
            'url' => 'eventtracker/history/issues',
        ])->add('actions', [
            'label' => $this->translate('Actions'),
            'url' => 'eventtracker/history/actions'
        ]);
    }
}
