<?php

use Icinga\Module\Eventtracker\ProvidedHook\Eventtracker\ScomIssueHook;

$this->provideHook('eventtracker/Issue', ScomIssueHook::class);
$this->provideHook('monitoring/HostActions');
$this->provideHook('monitoring/ServiceActions');
