<?php

use Icinga\Module\Eventtracker\ProvidedHook\Eventtracker\ScomIssueHook;

$this->provideHook('eventtracker/Issue', ScomIssueHook::class);
