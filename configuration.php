<?php

use Icinga\Module\Eventtracker\ProvidedHook\Eventtracker\ScomIssueHook;

if ($this->getConfig()->get('frontend', 'disabled', 'no') === 'yes') {
    return;
}

/** @var \Icinga\Application\Modules\Module $this */
$section = $this->menuSection(N_('Event Tracker'))
    ->setIcon('attention-circled')
    ->setUrl('eventtracker/dashboard')
    ->setPriority(62);
$section->add(N_('Issues'))->setUrl('eventtracker/issues')->setPriority(10);
$section->add(N_('Handled Issues'))
    ->setUrl('eventtracker/issues?status=acknowledged,in_downtime&columns=severity%,host_name,message,owner')
    ->setPriority(20);
$section->add(N_('Summaries'))->setUrl('eventtracker/summary/top10')->setPriority(30);

$this->provideSearchUrl('EventTracker', 'eventtracker/issues', 110);
$this->providePermission(
    'eventtracker/admin',
    $this->translate('Eventtracker admin')
);
$this->providePermission(
    'eventtracker/showsql',
    $this->translate('Allow to show SQL queries')
);
$this->providePermission(
    'eventtracker/operator',
    $this->translate('Operators are allowed to modify issues (Priority, Owner...)')
);
if ($this->getConfig()->get('scom', 'db_resource')) {
    $section->add(N_('SCOM Alerts'))
        ->setUrl('eventtracker/scom/alerts')
        ->setPermission('eventtracker/admin');
}
