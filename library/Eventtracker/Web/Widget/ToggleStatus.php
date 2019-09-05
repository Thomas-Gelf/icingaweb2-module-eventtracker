<?php

namespace Icinga\Module\Eventtracker\Web\Widget;

use gipfl\IcingaWeb2\Url;
use Icinga\Module\Eventtracker\Status;

class ToggleStatus extends ToggleFlagList
{
    public function __construct(Url $url)
    {
        parent::__construct($url, 'status');
    }

    protected function getListLabel()
    {
        return $this->translate('Status');
    }

    protected function getDefaultSelection()
    {
        $selection = [
            Status::OPEN,
        ];

        return array_combine($selection, $selection);
    }

    protected function getOptions()
    {
        return \array_reverse(Status::ENUM, true);
    }
}
