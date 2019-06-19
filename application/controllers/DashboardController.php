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
            $this->addSingleTab('Events');
        }

        $zoomLevel = '100%';
        $zoom = Html::tag('div', [
            'style' => "width: $zoomLevel; font-size: $zoomLevel;",
        ]);
        $subDash = Html::tag('div', ['class' => 'dashboard']);
        $subDash->add([
            new Dashlet('eventtracker/issues?sort=severity%20DESC', 'Host-Probleme'),
            new Dashlet('eventtracker/issues?sort=severity%20ASC', 'DB-Probleme'),
            new Dashlet('eventtracker/issues?q=ho&sort=severity%20DESC', 'Andere Probleme'),
        ]);
        $zoom->add($subDash);

        $subDash = Html::tag('div', ['class' => 'dashboard']);
        $subDash->add([
            new Dashlet('eventtracker/issues?sort=severity%20DESC&q=aha', 'Neue Probleme'),
            new Dashlet('eventtracker/issues?sort=severity%20ASC&q=net', 'Netze'),
            new Dashlet('eventtracker/issues?q=UNIX&sort=severity%20DESC', 'UNIX'),
        ]);
        $zoom->add($subDash);

        $this->content()->add($zoom);
    }
}
