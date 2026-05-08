<?php

namespace Icinga\Module\Eventtracker\Web\Widget;

use gipfl\IcingaWeb2\Widget\Tabs;
use gipfl\Translation\TranslationHelper;

class SummaryTabs extends Tabs
{
    use TranslationHelper;

    private bool $isHistory;

    public function __construct(bool $isHistory = false)
    {
        // We are not a BaseElement, not yet
        $this->isHistory = $isHistory;
        $this->assemble();
    }

    protected function assemble()
    {
        if ($this->isHistory) {
            $baseUrl = 'eventtracker/historysummary';
        } else {
            $baseUrl = 'eventtracker/summary';
        }
        $this->add('top10', [
            'label' => $this->translate('Top10'),
            'url'   => "$baseUrl/top10",
        ])->add('classes', [
            'label' => $this->translate('Object Classes'),
            'url'   => "$baseUrl/classes",
        ])->add('objects', [
            'label' => $this->translate('Object Names'),
            'url'   => "$baseUrl/objects",
        ])->add('hosts', [
            'label' => $this->translate('Hosts'),
            'url'   => "$baseUrl/hosts",
        ])->add('owners', [
            'label' => $this->translate('Owner'),
            'url'   => "$baseUrl/owners",
        ])->add('inputs', [
            'label' => $this->translate('Input'),
            'url'   => "$baseUrl/inputs",
        ])->add('senders', [
            'label' => $this->translate('Sender'),
            'url'   => "$baseUrl/senders",
        ]);
    }
}
