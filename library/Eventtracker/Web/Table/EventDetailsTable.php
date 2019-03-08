<?php

namespace Icinga\Module\Eventtracker\Web\Table;

use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Widget\NameValueTable;
use gipfl\Translation\TranslationHelper;
use Icinga\Module\Eventtracker\Hook\EventActionsHook;
use Icinga\Module\Eventtracker\Incident;
use Icinga\Module\Eventtracker\Time;
use Icinga\Web\Hook;
use ipl\Html\Html;

class EventDetailsTable extends NameValueTable
{
    use TranslationHelper;

    public function __construct(Incident $incident)
    {
        $this->addNameValuePairs([
            $this->translate('Since')    => Time::agoFormatted($incident->get('ts_first_event')),
            $this->translate('Status')   => $incident->get('status'),
            $this->translate('Severity') => $incident->get('severity'),
            $this->translate('Priority') => $incident->get('priority'),
            $this->translate('Host')     => $incident->get('host_name'),
            $this->translate('Object')   => $incident->get('object_name'),
            $this->translate('Class')    => $incident->get('object_class'),
            $this->translate('Message')  => $incident->get('message'),
            $this->translate('Owner')    => $incident->get('owner', '-'),
        ]);

        $this->addNameValuePairs($incident->getProperties());
    }
}
