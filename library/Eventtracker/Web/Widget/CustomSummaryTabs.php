<?php

namespace Icinga\Module\Eventtracker\Web\Widget;

use gipfl\IcingaWeb2\Widget\Tabs;
use gipfl\Translation\TranslationHelper;
use Icinga\Application\Config;

class CustomSummaryTabs extends Tabs
{
    use TranslationHelper;

    public function __construct()
    {
        // We are not a BaseElement, not yet
        $this->assemble();
    }

    protected function assemble()
    {
        $this->add('index', [
            'label' => $this->translate('Custom Summaries'),
            'url'   => 'eventtracker/custom-summaries',
        ]);

        foreach (Config::module('eventtracker', 'customSummaries') as $title => $section) {
            $this->add($title, [
                'label' => $section->get('label', $title),
                'url'   => 'eventtracker/custom-summaries',
                'urlParams' => ['summary' => $title],
            ]);
        }
    }
}
