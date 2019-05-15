<?php

namespace Icinga\Module\Eventtracker\Web\Table;

use gipfl\IcingaWeb2\Widget\NameValueTable;
use gipfl\Translation\TranslationHelper;
use Icinga\Module\Eventtracker\Incident;
use Icinga\Module\Eventtracker\Time;
use Icinga\Module\Eventtracker\Web\HtmlPurifier;

class EventDetailsTable extends NameValueTable
{
    use TranslationHelper;

    // HINT: This renders nothing right now.

    public function __construct(Incident $incident)
    {
        $this->addNameValuePairs([
            // $this->translate('Since')    => Time::agoFormatted($incident->get('ts_first_event')),
            // $this->translate('Status')   => $incident->get('status'),
            // $this->translate('Severity') => $incident->get('severity'),
            // $this->translate('Priority') => $incident->get('priority'),
            // $this->translate('Host')     => $incident->get('host_name'),
            // $this->translate('Object')   => $incident->get('object_name'),
            // $this->translate('Class')    => $incident->get('object_class'),
            // $this->translate('Message')  => HtmlPurifier::process($incident->get('message')),
            // this->translate('Owner')    => $incident->get('owner', '-'),
        ]);

        $blacklist = [
            'severity',
            'priority',
            'status',
            'message',
            'host_name',
            'owner',
            'cnt_events',
            'object_name',
            'object_class',
            'incident_uuid',
            'ts_first_event',
            'ts_last_modified',
            'ts_expiration',

            'sender_id',
            'sender_event_id',
            'sender_event_checksum',
        ];
        foreach ($incident->getProperties() as $name => $value) {
            if (in_array($name, $blacklist)) {
                continue;
            }
            $this->addNameValueRow($name, $value);
        }
    }
}
