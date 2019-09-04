<?php

namespace Icinga\Module\Eventtracker\Web\Widget;

use gipfl\IcingaWeb2\Widget\Tabs;
use gipfl\Translation\TranslationHelper;

class SummaryTabs extends Tabs
{
    use TranslationHelper;

    public function __construct()
    {
        // We are not a BaseElement, not yet
        $this->assemble();
    }

    protected function assemble()
    {
        $this->add('top10', [
            'label' => $this->translate('Top10'),
            'url'   => 'eventtracker/summary/top10',
        ])->add('classes', [
            'label' => $this->translate('Object Classes'),
            'url'   => 'eventtracker/summary/classes',
        ])->add('objects', [
            'label' => $this->translate('Object Names'),
            'url'   => 'eventtracker/summary/objects',
        ])->add('hosts', [
            'label' => $this->translate('Hosts'),
            'url'   => 'eventtracker/summary/hosts',
        ])->add('owners', [
            'label' => $this->translate('Owner'),
            'url'   => 'eventtracker/summary/owners',
        ])->add('senders', [
            'label' => $this->translate('Sender'),
            'url'   => 'eventtracker/summary/senders',
        ]);
    }
}
