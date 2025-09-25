<?php

namespace Icinga\Module\Eventtracker\Modifier;

use gipfl\Translation\TranslationHelper;
use Icinga\Module\Eventtracker\Syslog\SyslogSeverity;
use ipl\Html\Html;
use ipl\Html\ValidHtml;

class RequireNamedSyslogSeverity extends BaseModifier
{
    use TranslationHelper;

    protected static ?string $name = 'Numeric Syslog Severity Map';

    protected function simpleTransform($value)
    {
        return SyslogSeverity::wantName($value);
    }

    public function describe(string $propertyName): ValidHtml
    {
        return Html::sprintf(
            $this->translate('Make %s a valid named Syslog severity'),
            Html::tag('strong', $propertyName),
        );
    }
}
