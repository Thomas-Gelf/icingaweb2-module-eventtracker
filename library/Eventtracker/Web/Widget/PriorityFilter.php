<?php

namespace Icinga\Module\Eventtracker\Web\Widget;

use Icinga\Module\Eventtracker\Priority;

class PriorityFilter extends BaseEnumPropertyFilter
{
    protected $property = 'priority';

    protected $enum = Priority::ENUM;
}
