<?php

namespace Icinga\Module\Eventtracker\Controllers;

use gipfl\IcingaWeb2\CompatController;
use gipfl\IcingaWeb2\Link;
use Icinga\Authentication\Auth;
use Icinga\Module\Eventtracker\Db\EventSummaryByPriority;
use Icinga\Module\Eventtracker\Db\EventSummaryBySeverity;
use Icinga\Module\Eventtracker\DbFactory;
use Icinga\Module\Eventtracker\Uuid;
use Icinga\Module\Eventtracker\Web\Table\EventsTable;
use Icinga\Module\Eventtracker\Web\Widget\AdditionalTableActions;
use Icinga\Module\Eventtracker\Web\Widget\PriorityFilter;
use Icinga\Module\Eventtracker\Web\Widget\SeverityFilter;
use Icinga\Module\Eventtracker\Web\Widget\TogglePriorities;
use Icinga\Module\Eventtracker\Web\Widget\ToggleSeverities;
use Icinga\Module\Eventtracker\Web\Widget\ToggleStatus;
use Icinga\Web\Widget\Tabextension\DashboardAction;
use ipl\Html\Html;

class IssuesController extends CompatController
{
    protected function showCompact()
    {
        return $this->params->get('view') === 'compact';
    }

    protected function applyFilters(EventsTable $table)
    {
        $table->search($this->params->get('q'));
        $this->columnFilter($table, 'host_name', 'hosts', $this->translate('Hosts: %s'));
        $this->columnFilter($table, 'object_class', 'classes', $this->translate('Classes: %s'));
        $this->columnFilter($table, 'object_name', 'objects', $this->translate('Objects: %s'));
        $this->columnFilter($table, 'owner', 'owners', $this->translate('Owners: %s'));
        $this->columnFilter($table, 'sender_name', 'senders', $this->translate('Sender: %s'));
    }

    protected function columnFilter(EventsTable $table, $column, $type, $title)
    {
        $compact = $this->showCompact();
        if ($this->params->has($column)) {
            $value = $this->params->get($column);

            // TODO: move this elsewhere, here we shouldn't need to care about DB structure:
            if ($column === 'sender_name') {
                $table->joinSenders();
                $column = "s.$column";
            }
            if (strlen($value)) {
                $table->getQuery()->where("$column = ?", $value);
            } else {
                $table->getQuery()->where("$column IS NULL");
                $value = $this->translate('- none -');
            }
            if ($compact) {
                return;
            }
            $this->content()->add(
                Link::create(
                    sprintf($title, $value),
                    $this->url()->without($column),
                    null,
                    ['data-base-target' => '_self']
                )
            );
        } else {
            if ($compact) {
                return;
            }
            $this->content()->add(
                Link::create(
                    sprintf($title, $this->translate('all')),
                    "eventtracker/summary/$type",
                    null,
                    ['data-base-target' => '_next']
                )
            );
        }
        $this->content()->add(Html::tag('br'));
    }

    public function indexAction()
    {
        $this->setAutorefreshInterval(5);
        $db = DbFactory::db();

        $table = new EventsTable($db);
        $this->applyFilters($table);
        $prioSummary = new EventSummaryByPriority($table->getQuery());
        $sevSummary = new EventSummaryBySeverity($table->getQuery());

        $prioSummary->filterByUrl($this->url());
        $sevSummary->filterByUrl($this->url());
        if ($this->getRequest()->isApiRequest()) {
            $table->handleSortUrl($this->url());
            $result = $table->fetch();
            foreach ($result as & $row) {
                $row->issue_uuid = Uuid::toHex($row->issue_uuid);
            }
            $flags = JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE;
            echo json_encode($result, $flags);
            exit;
        }
        if (! $this->url()->getParam('sort')) {
            $this->url()->setParam('sort', 'severity DESC');
        }
        if ($this->showCompact()) {
            $table->setNoHeader();
            $this->content()->add($table);
            $table->handleSortUrl($this->url());
            $table->getQuery()->limit(10);
        } else {
            $this->addSingleTab('Issues');
            $this->setTitle('Event Tracker');
            $this->controls()->addTitle('Current Issues', new SeverityFilter($sevSummary->fetch($db), $this->url()));
            $this->actions()->add([
                Html::tag('ul', ['class' => 'nav'], [
                    new TogglePriorities($this->url()),
                    new ToggleSeverities($this->url()),
                    new ToggleStatus($this->url()),
                ])
            ]);
            (new AdditionalTableActions($table, Auth::getInstance(), $this->url()))
                ->appendTo($this->actions());
            $table->handleSortUrl($this->url());
            $table->renderTo($this);
        }

        if (! $this->showCompact()) {
            $this->tabs()->extend(new DashboardAction());
        }
    }
}
