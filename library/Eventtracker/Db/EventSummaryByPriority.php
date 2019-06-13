<?php

namespace Icinga\Module\Eventtracker\Db;

use Icinga\Module\Eventtracker\Priority;

class EventSummaryByPriority extends EventSummaryByProperty
{
    const PROPERTY = 'severity';

    const CLASS_NAME = Priority::class;
}
