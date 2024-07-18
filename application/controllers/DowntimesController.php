<?php

namespace Icinga\Module\Eventtracker\Controllers;

use gipfl\IcingaWeb2\Widget\Tabs;
use Icinga\Module\Eventtracker\Web\Table\ActiveDowntimesTable;
use Icinga\Module\Eventtracker\Web\Table\ScheduledDowntimesTable;

class DowntimesController extends Controller
{
    public function activeAction()
    {
        $this->setAutorefreshInterval(15);
        $this->downtimesTabs()->activate('active');
        $table = new ActiveDowntimesTable($this->db());
        $table->renderTo($this);
    }

    public function scheduledAction()
    {
        $this->setAutorefreshInterval(15);
        $this->downtimesTabs()->activate('scheduled');
        $table = new ScheduledDowntimesTable($this->db());
        $table->renderTo($this);
    }

    protected function downtimesTabs(): Tabs
    {
        return $this->tabs()->add('active', [
            'label' => $this->translate('Active Downtimes'),
            'url'   => 'eventtracker/downtimes/active',
        ])->add('scheduled', [
            'label' => $this->translate('Scheduled Downtimes'),
            'url'   => 'eventtracker/downtimes/scheduled',
        ]);
    }
}
