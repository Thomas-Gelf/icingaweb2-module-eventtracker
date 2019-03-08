<?php

namespace Icinga\Module\Eventtracker;

class Event
{
    use PropertyHelpers;

    protected $properties = [
        'host_name'       => null,
        'object_name'     => null,
        'object_class'    => null,
        'severity'        => null,
        'priority'        => null,
        'message'         => null,
        'event_timeout'   => null,
        'sender_event_id' => null,
        'sender_id'       => null,
    ];

    public function getChecksum()
    {
        return sha1(json_encode([
            $this->get('host_name'),
            $this->get('object_class'),
            $this->get('object_name'),
            $this->get('sender_id'),
            $this->get('sender_event_id'),
        ]), true);
    }

    public function isProblem()
    {
        // TODO: OK is not a problem.
        return true;
        $ok = [
            'notice',
            'informational',
            'debug',
        ];

        return ! in_array($this->get('severity'), $ok);
    }
}
