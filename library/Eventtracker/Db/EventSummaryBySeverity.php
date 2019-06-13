<?php

namespace Icinga\Module\Eventtracker\Db;

use Icinga\Module\Eventtracker\Severity;

class EventSummaryBySeverity extends EventSummaryByProperty
{
    const PROPERTY = 'severity';

    const CLASS_NAME = Severity::class;
}
