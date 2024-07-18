<?php

namespace Icinga\Module\Eventtracker\Web\Table;

class ActiveDowntimesTable extends ScheduledDowntimesTable
{
    public function prepareQuery()
    {
        return parent::prepareQuery()->where('is_active = ?', 'y');
    }
}
