<?php

/** @var \Icinga\Application\Modules\Module $this */
$section = $this->menuSection(N_('Event Tracker'))
    ->setIcon('tasks')
    ->setUrl('eventtracker/events')
    ->setPriority(86);
