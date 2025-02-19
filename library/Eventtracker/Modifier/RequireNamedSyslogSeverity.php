<?php

namespace Icinga\Module\Eventtracker\Modifier;

use Icinga\Module\Eventtracker\Syslog\SyslogSeverity;
use ipl\Html\Html;
use ipl\Html\ValidHtml;

class RequireNamedSyslogSeverity extends BaseModifier
{
    protected static ?string $name = 'Numeric Syslog Severity Map';

    protected function simpleTransform($value)
    {
        return SyslogSeverity::wantName($value);
    }

    public function describe(string $propertyName): ValidHtml
    {
        return Html::sprintf(
            'Make %s a valid named Syslog severity',
            Html::tag('strong', $propertyName)
        );
    }
}
