<?php

namespace Icinga\Module\Eventtracker\Controllers;

use gipfl\IcingaWeb2\CompatController;
use Icinga\Module\Eventtracker\Web\Widget\Dashlet;
use ipl\Html\Html;

class DashboardController extends CompatController
{
    public function indexAction()
    {
        $this->setTitle($this->translate('Event Tracker Dashboard'));
        $this->content()->addAttributes(['class' => 'dashboard']);
        if (! $this->hasParam('showFullscreen')) {
            $this->addSingleTab($this->translate('Dashboard'));
        }

        $zoomLevel = '100%';
        $zoom = Html::tag('div', [
            'style' => "width: $zoomLevel; font-size: $zoomLevel;",
        ]);
        $subDash = Html::tag('div', ['class' => 'dashboard']);
        $subDash->add([
            new Dashlet('eventtracker/issues?status=open', $this->translate('Unhandled Events')),
            new Dashlet('eventtracker/issues?status=acknowledged,in_downtime', $this->translate('Handled Issues')),
            new Dashlet('eventtracker/summary/top10', $this->translate('Top Issue Summary by:')),
        ]);
        $zoom->add($subDash);
        /*
        $subDash = Html::tag('div', ['class' => 'dashboard']);
        $subDash->add([
            new Dashlet('eventtracker/issues?sort=severity%20DESC&q=aha', 'Neue Probleme'),
            new Dashlet('eventtracker/issues?sort=severity%20ASC&q=net', 'Netze'),
            new Dashlet('eventtracker/issues?q=UNIX&sort=severity%20DESC', 'UNIX'),
        ]);
        $zoom->add($subDash);
        */
        $this->content()->add($zoom);
    }
}
