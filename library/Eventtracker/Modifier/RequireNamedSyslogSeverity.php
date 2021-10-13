<?php

namespace Icinga\Module\Eventtracker\Modifier;

use Icinga\Module\Eventtracker\Syslog\SyslogSeverity;

class RequireNamedSyslogSeverity extends BaseModifier
{
    protected static $name = 'Numeric Syslog Severity Map';

    protected function simpleTransform($value)
    {
        return SyslogSeverity::wantName($value);
    }
}
