<?php

/** @var \Icinga\Application\Modules\Module $this */
$section = $this->menuSection(N_('Event Tracker'))
    ->setIcon('tasks')
    ->setUrl('eventtracker/dashboard')
    ->setPriority(86);
$section->add(N_('Issues'))->setUrl('eventtracker/issues');
$section->add(N_('Summaries'))->setUrl('eventtracker/summary/top10');

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
