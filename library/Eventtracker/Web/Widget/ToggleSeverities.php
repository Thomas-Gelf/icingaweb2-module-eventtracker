<?php

namespace Icinga\Module\Eventtracker\Web\Widget;

use gipfl\IcingaWeb2\Url;
use Icinga\Module\Eventtracker\Severity;

class ToggleSeverities extends ToggleFlagList
{
    public function __construct(Url $url)
    {
        parent::__construct($url, 'severity');
    }

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

    protected function getListLabel()
    {
        return $this->translate('Severities');
    }

    protected function getOptions()
    {
        return \array_reverse(Severity::ENUM, true);
    }
}
