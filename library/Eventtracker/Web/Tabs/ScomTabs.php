<?php

namespace Icinga\Module\Eventtracker\Web\Tabs;

use gipfl\Translation\TranslationHelper;
use gipfl\IcingaWeb2\Widget\Tabs;

class ScomTabs extends Tabs
{
    use TranslationHelper;

    protected $rule;

    public function __construct()
    {
        $this->assemble();
    }

    protected function assemble()
    {
        $this->add('alerts', [
            'url'       => 'eventtracker/scom/alerts',
            'label'     => $this->translate('SCOM Alerts'),
        ])->add('viewalerts', [
            'url'       => 'eventtracker/scom/viewalerts',
            'label'     => $this->translate('Alerts (from View)'),
        ])->add('perfcounters', [
            'url'       => 'eventtracker/mssql/performance',
            'label'     => $this->translate('MSSQL Perfcounters'),
        ])->add('processes', [
            'url'       => 'eventtracker/mssql/processes',
            'label'     => $this->translate('MSSQL Processes'),
        ]);
    }
}
