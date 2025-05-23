<?php

/** @var \Icinga\Application\Modules\Module $this */
if ($this->getConfig()->get('ui', 'disabled', 'no') === 'yes') {
    return;
}

$section = $this->menuSection(N_('Event Tracker'))
    ->setIcon('warning-empty')
    ->setUrl('eventtracker/dashboard')
    ->setPriority(17);
$section->add(N_('Issues'))->setUrl('eventtracker/issues')->setPriority(10);
$section->add(N_('Handled Issues'))
    ->setUrl(
        'eventtracker/issues?status=acknowledged,in_downtime'
        . '&columns=severity,host_name,message,owner,ticket_ref'
    )
    ->setPriority(20);
$section->add(N_('Summaries'))->setUrl('eventtracker/summary/top10')->setPriority(30);
$section->add(N_('History'))->setUrl('eventtracker/history/issues')->setPriority(30);
// $section->add(N_('Downtimes'))->setUrl('eventtracker/downtimes')->setPriority(40);
$section->add(N_('Configuration'))
    ->setUrl('eventtracker/configuration')
    ->setPriority(70)
    ->setPermission('eventtracker/admin');

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
$this->provideRestriction(
    'eventtracker/ignoreInputs',
    $this->translate('Comma-separated list of Input UUIDs to ignore')
);
$this->provideRestriction(
    'eventtracker/filterInputs',
    $this->translate('Comma-separated list of Input UUIDs to show')
);
if ($this->getConfig()->get('scom', 'db_resource')) {
    // $section->add(N_('SCOM Alerts'))
    //     ->setUrl('eventtracker/scom/alerts')
    //     ->setPermission('eventtracker/admin');
}
