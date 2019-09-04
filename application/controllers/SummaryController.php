<?php

namespace Icinga\Module\Eventtracker\Controllers;

use gipfl\IcingaWeb2\CompatController;
use Icinga\Module\Eventtracker\DbFactory;
use Icinga\Module\Eventtracker\Status;
use Icinga\Module\Eventtracker\Web\Table\BaseSummaryTable;
use Icinga\Module\Eventtracker\Web\Table\HostNameSummaryTable;
use Icinga\Module\Eventtracker\Web\Table\ObjectClassSummaryTable;
use Icinga\Module\Eventtracker\Web\Table\ObjectNameSummaryTable;
use Icinga\Module\Eventtracker\Web\Table\OwnerSummaryTable;
use Icinga\Module\Eventtracker\Web\Table\SenderSummaryTable;
use Icinga\Module\Eventtracker\Web\Widget\SummaryTabs;
use ipl\Html\Html;

class SummaryController extends CompatController
{
    public function classesAction()
    {
        $this->addTitleWithType($this->translate('Object Class'));
        $this->setAutorefreshInterval(10);
        (new ObjectClassSummaryTable(DbFactory::db()))->renderTo($this);
        $this->tabs(new SummaryTabs())->activate('classes');
    }

    public function objectsAction()
    {
        $this->addTitleWithType($this->translate('Object Name'));
        $this->setAutorefreshInterval(10);
        (new ObjectNameSummaryTable(DbFactory::db()))->renderTo($this);
        $this->tabs(new SummaryTabs())->activate('objects');
    }

    public function hostsAction()
    {
        $this->addTitleWithType($this->translate('Hostname'));
        $this->setAutorefreshInterval(10);
        (new HostNameSummaryTable(DbFactory::db()))->renderTo($this);
        $this->tabs(new SummaryTabs())->activate('hosts');
    }

    public function ownersAction()
    {
        $this->addTitleWithType($this->translate('Owner'));
        $this->setAutorefreshInterval(10);
        (new OwnerSummaryTable(DbFactory::db()))->renderTo($this);
        $this->tabs(new SummaryTabs())->activate('objects');
    }

    public function sendersAction()
    {
        $this->addTitleWithType($this->translate('Sender'));
        $this->setAutorefreshInterval(10);
        (new SenderSummaryTable(DbFactory::db()))->renderTo($this);
        $this->tabs(new SummaryTabs())->activate('senders');
    }

    public function top10Action()
    {
        if (! $this->showCompact()) {
            $this->tabs(new SummaryTabs())->activate('top10');
            $this->addTitle($this->translate('Top Issue Summary by:'));
        }

        $db = DbFactory::db();
        $this->setAutorefreshInterval(10);
        $tables = [
            $this->translate('Object Class') => new ObjectClassSummaryTable($db),
            $this->translate('Object Name')  => new ObjectNameSummaryTable($db),
            $this->translate('Hostname')     => new HostNameSummaryTable($db),
            $this->translate('Owner')        => new OwnerSummaryTable($db),
            $this->translate('Sender')       => new SenderSummaryTable($db),
        ];
        /** @var BaseSummaryTable $table */
        foreach ($tables as $title => $table) {
            // $this->content()->add(Html::tag('h2', $title));
            if ($this->showCompact()) {
                $table->setAttribute('data-base-target', '_next');
            }
            $table->getQuery()->limit(10)->where('i.status = ?', Status::OPEN);
            $this->content()->add($table);
        }
    }

    protected function addTitleWithType($type)
    {
        $this->addTitle(sprintf(
            $this->translate('Issue Summary by %s'),
            $type
        ));
    }

    protected function showCompact()
    {
        return $this->params->get('view') === 'compact';
    }
}
