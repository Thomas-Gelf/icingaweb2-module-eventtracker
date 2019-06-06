<?php

namespace Icinga\Module\Eventtracker\Web\Table;

use gipfl\IcingaWeb2\Widget\NameValueTable;
use gipfl\Translation\TranslationHelper;
use Icinga\Module\Eventtracker\Issue;
use Icinga\Module\Eventtracker\Time;
use Icinga\Module\Eventtracker\Web\HtmlPurifier;

class EventDetailsTable extends NameValueTable
{
    use TranslationHelper;

    // HINT: This renders nothing right now.

    public function __construct(Issue $issue)
    {
        $this->addNameValuePairs([
            // $this->translate('Since')    => Time::agoFormatted($issue->get('ts_first_event')),
            // $this->translate('Status')   => $issue->get('status'),
            // $this->translate('Severity') => $issue->get('severity'),
            // $this->translate('Priority') => $issue->get('priority'),
            // $this->translate('Host')     => $issue->get('host_name'),
            // $this->translate('Object')   => $issue->get('object_name'),
            // $this->translate('Class')    => $issue->get('object_class'),
            // $this->translate('Message')  => HtmlPurifier::process($issue->get('message')),
            // this->translate('Owner')    => $issue->get('owner', '-'),
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
            'issue_uuid',
            'ts_first_event',
            'ts_last_modified',
            'ts_expiration',

            'sender_id',
            'sender_event_id',
            'sender_event_checksum',
        ];
        foreach ($issue->getProperties() as $name => $value) {
            if (in_array($name, $blacklist)) {
                continue;
            }
            $this->addNameValueRow($name, $value);
        }
    }
}
