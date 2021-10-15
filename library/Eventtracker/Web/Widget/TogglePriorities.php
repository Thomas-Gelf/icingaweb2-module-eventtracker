<?php

namespace Icinga\Module\Eventtracker\Web\Widget;

use gipfl\IcingaWeb2\Url;
use Icinga\Module\Eventtracker\Priority;

class TogglePriorities extends ToggleFlagList
{
    public function __construct(Url $url)
    {
        parent::__construct($url, 'priority');
    }

    protected function getListLabel()
    {
        return $this->translate('Priorities');
    }

    protected function getOptions()
    {
        return \array_reverse(Priority::ENUM_LIST, true);
    }
}
