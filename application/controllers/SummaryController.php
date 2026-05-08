<?php

namespace Icinga\Module\Eventtracker\Controllers;

use gipfl\ZfDb\Select;
use Icinga\Module\Eventtracker\Status;
use Icinga\Module\Eventtracker\Web\Table\BaseSummaryTable;
use Icinga\Module\Eventtracker\Web\Table\HostNameSummaryTable;
use Icinga\Module\Eventtracker\Web\Table\InputSummaryTable;
use Icinga\Module\Eventtracker\Web\Table\ObjectClassSummaryTable;
use Icinga\Module\Eventtracker\Web\Table\ObjectNameSummaryTable;
use Icinga\Module\Eventtracker\Web\Table\OwnerSummaryTable;
use Icinga\Module\Eventtracker\Web\Table\SenderSummaryTable;
use Icinga\Module\Eventtracker\Web\Widget\SummaryTabs;
use ipl\Html\Html;

class SummaryController extends Controller
{
    protected string $tableName = 'issue';

    protected bool $isHistory = false;
    public function classesAction()
    {
        $this->addTitleForType($this->translate('Object Class'));
        $this->setAutorefreshInterval(10);
        (new ObjectClassSummaryTable($this->db(), $this->tableName))->renderTo($this);
        $this->tabs(new SummaryTabs($this->isHistory))->activate('classes');
    }

    public function objectsAction()
    {
        $this->addTitleForType($this->translate('Object Name'));
        $this->setAutorefreshInterval(10);
        (new ObjectNameSummaryTable($this->db(), $this->tableName))->renderTo($this);
        $this->tabs(new SummaryTabs($this->isHistory))->activate('objects');
    }
    public function hostsAction()
    {
        $this->addTitleForType($this->translate('Hostname'));
        $this->setAutorefreshInterval(10);
        (new HostNameSummaryTable($this->db(), $this->tableName))->renderTo($this);
        $this->tabs(new SummaryTabs($this->isHistory))->activate('hosts');
    }

    public function ownersAction()
    {
        $this->addTitleForType($this->translate('Owner'));
        $this->setAutorefreshInterval(10);
        (new OwnerSummaryTable($this->db(), $this->tableName))->renderTo($this);
        $this->tabs(new SummaryTabs($this->isHistory))->activate('owners');
    }

    public function inputsAction()
    {
        $this->addTitleForType($this->translate('Input'));
        $this->setAutorefreshInterval(10);
        (new InputSummaryTable($this->db(), $this->tableName))->renderTo($this);
        $this->tabs(new SummaryTabs($this->isHistory))->activate('inputs');
    }

    public function sendersAction()
    {
        $this->addTitleForType($this->translate('Sender'));
        $this->setAutorefreshInterval(10);
        (new SenderSummaryTable($this->db(), $this->tableName))->renderTo($this);
        $this->tabs(new SummaryTabs($this->isHistory))->activate('senders');
    }

    public function top10Action()
    {
        if (! $this->showCompact()) {
            $this->tabs(new SummaryTabs($this->isHistory))->activate('top10');
            $this->addTitle($this->getTop10Title());
        }

        $db = $this->db();
        $this->setAutorefreshInterval(10);
        $main = Html::tag('div', [
            'class' => 'summary-tables'
        ]);
        $tables = [
            $this->translate('Object Class') => new ObjectClassSummaryTable($db, $this->tableName),
            $this->translate('Object Name')  => new ObjectNameSummaryTable($db, $this->tableName),
            $this->translate('Hostname')     => new HostNameSummaryTable($db, $this->tableName),
            $this->translate('Owner')        => new OwnerSummaryTable($db, $this->tableName),
            $this->translate('Input')        => new InputSummaryTable($db, $this->tableName),
            $this->translate('Sender (Old)') => new SenderSummaryTable($db, $this->tableName),
        ];
        /** @var BaseSummaryTable $table */
        foreach ($tables as $title => $table) {
            // $this->content()->add(Html::tag('h2', $title));
            if ($this->showCompact()) {
                $table->setAttribute('data-base-target', '_next');
            }
            $table->setAttribute('data-base-target', '_next');
            $table->getQuery()->limit(10);
            $this->applyStatusLimit($table->getQuery());
            $main->add(Html::tag('div', $table));
        }
        $this->content()->add($main);
    }

    protected function applyStatusLimit(Select $select): void
    {
        $select->where('i.status = ?', Status::OPEN);
    }

    protected function addTitleForType(string $type): void
    {
        $this->addTitle($this->getTitleForType($type));
    }

    protected function getTitleForType(string $type): string
    {
        return sprintf($this->translate('Issue Summary by %s'), $type);
    }

    protected function getTop10Title(): string
    {
        return sprintf($this->translate('Top Issue Summary by:'));
    }

    protected function showCompact()
    {
        return $this->params->get('view') === 'compact';
    }
}
