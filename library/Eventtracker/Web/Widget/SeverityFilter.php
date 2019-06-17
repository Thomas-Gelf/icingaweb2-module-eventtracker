<?php

namespace Icinga\Module\Eventtracker\Web\Widget;

use Icinga\Module\Eventtracker\Severity;

class SeverityFilter extends BaseEnumPropertyFilter
{
    protected $property = 'severity';

    protected $enum = Severity::ENUM;

    protected function getDefaultSelection()
    {
        $selection = [
            Severity::EMERGENCY,
            Severity::ALERT,
            Severity::CRITICAL,
            Severity::ERROR,
            Severity::WARNING,
        ];

        return array_combine($selection, $selection);
    }
}
