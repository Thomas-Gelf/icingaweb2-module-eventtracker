<?php

namespace Icinga\Module\Eventtracker\Controllers;

use gipfl\IcingaWeb2\CompatController;
use gipfl\IcingaWeb2\Icon;
use gipfl\IcingaWeb2\Link;
use Icinga\Authentication\Auth;
use Icinga\Module\Eventtracker\Db\EventSummaryBySeverity;
use Icinga\Module\Eventtracker\DbFactory;
use Icinga\Module\Eventtracker\Uuid;
use Icinga\Module\Eventtracker\Web\Table\BaseTable;
use Icinga\Module\Eventtracker\Web\Table\IssuesTable;
use Icinga\Module\Eventtracker\Web\Widget\AdditionalTableActions;
use Icinga\Module\Eventtracker\Web\Widget\SeverityFilter;
use Icinga\Module\Eventtracker\Web\Widget\TogglePriorities;
use Icinga\Module\Eventtracker\Web\Widget\ToggleSeverities;
use Icinga\Module\Eventtracker\Web\Widget\ToggleStatus;
use Icinga\Web\Widget\Tabextension\DashboardAction;
use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;

class IssuesController extends CompatController
{
    protected function showCompact()
    {
        return $this->params->get('view') === 'compact';
    }

    protected function applyFilters(IssuesTable $table)
    {
        $table->search($this->params->get('q'));
        $main = Html::tag('ul', ['class' => 'nav']);
        $sub = Html::tag('ul');
        $main->add(Html::tag('li', null, [Link::create('Filters', '#', null, [
            'class' => 'icon-angle-double-down'
        ]), $sub]));
        $this->columnFilter($table, $sub, 'host_name', 'hosts', $this->translate('Hosts: %s'));
        $this->columnFilter($table, $sub, 'object_class', 'classes', $this->translate('Classes: %s'));
        $this->columnFilter($table, $sub, 'object_name', 'objects', $this->translate('Objects: %s'));
        $this->columnFilter($table, $sub, 'owner', 'owners', $this->translate('Owners: %s'));
        $this->columnFilter($table, $sub, 'sender_name', 'senders', $this->translate('Sender: %s'));
        if (! $this->showCompact()) {
            $this->actions()->add($main);
        }
    }

    protected function createViewToggle()
    {
        $wide = $this->params->get('wide');
        if ($wide) {
            return Link::create(
                $this->translate('Compact'),
                $this->url()->without('wide'),
                null,
                [
                    'title' => $this->translate('Switch to compact mode'),
                    'class' => 'icon-resize-small'
                ]
            );
        } else {
            return Link::create(
                $this->translate('Full'),
                $this->url()->with('wide', true),
                null,
                [
                    'title' => $this->translate('Switch to compact mode'),
                    'class' => 'icon-resize-full'
                ]
            );
        }
    }

    protected function columnFilter(IssuesTable $table, BaseHtmlElement $parent, $column, $type, $title)
    {
        // $parent = $this->content();
        $li = Html::tag('li');
        $parent->add($li);
        $parent = $li;
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
            $parent->add(
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
            $parent->add(
                Link::create(
                    sprintf($title, $this->translate('all')),
                    "eventtracker/summary/$type",
                    null,
                    ['data-base-target' => '_next']
                )
            );
        }
        return;
        $this->content()->add(Html::tag('br'));
    }

    public function indexAction()
    {
        $this->setAutorefreshInterval(5);
        $db = DbFactory::db();

        $table = new IssuesTable($db, $this->url());
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
            $this->controls()->addTitle('Current Issues', $summary);
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

    protected function eventuallySendJson(BaseTable $table)
    {
        if ($this->getRequest()->isApiRequest() || $this->getParam('format') === 'json') {
            $table->ensureAssembled();
            $result = $table->fetch();
            foreach ($result as & $row) {
                $row->issue_uuid = Uuid::toHex($row->issue_uuid);
            }
            $flags = JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE;
            $this->getResponse()->setHeader('Content-Type', 'application/json', true)->sendHeaders();
            echo json_encode($result, $flags);
            exit;
        }
    }
}
