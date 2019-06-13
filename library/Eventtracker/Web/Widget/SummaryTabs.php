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
        $this->add('hosts', [
            'label' => $this->translate('Hosts'),
            'url'   => 'eventtracker/hosts',
        ])->add('classes', [
            'label' => $this->translate('Object Classes'),
            'url'   => 'eventtracker/classes',
        ])->add('objects', [
            'label' => $this->translate('Object Names'),
            'url'   => 'eventtracker/objects',
        ])->add('owners', [
            'label' => $this->translate('Owner'),
            'url'   => 'eventtracker/owners',
        ]);
    }
}
