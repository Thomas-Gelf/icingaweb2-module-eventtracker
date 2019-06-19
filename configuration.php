<?php

/** @var \Icinga\Application\Modules\Module $this */
$section = $this->menuSection(N_('Event Tracker'))
    ->setIcon('tasks')
    ->setUrl('eventtracker/issues')
    ->setPriority(86);

$this->providePermission(
    'eventtracker/operator',
    $this->translate('Operators are allowed to modify issues (Priority, Owner...)')
);
