<?php

namespace Icinga\Module\Eventtracker;

use Ramsey\Uuid\Uuid;

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
        'input_uuid'      => null,
        'sender_event_id' => null,
        'sender_id'       => null,
        'attributes'      => null,
        'acknowledge'     => null,
        'clear'           => null,
    ];

    public function getChecksum()
    {
        $hexUuid = $this->getHexInputUuid();

        if ($hexUuid === null) {
            // Legacy checksum
            return sha1(json_encode([
                $this->get('host_name'),
                $this->get('object_class'),
                $this->get('object_name'),
                $this->get('sender_id'),
                $this->get('sender_event_id'),
            ]), true);
        }

        return sha1(json_encode([
            $this->get('host_name'),
            $this->get('object_class'),
            $this->get('object_name'),
            $this->get('sender_id'),
            $this->get('sender_event_id'),
            $hexUuid,
        ]), true);
    }

    protected function getHexInputUuid()
    {
        $uuid = $this->get('input_uuid');
        if ($uuid === null) {
            return null;
        }

        return Uuid::fromBytes($uuid)->toString();
    }

    public function isAcknowledged()
    {
        return (bool) $this->get('acknowledge');
    }

    public function hasBeenCleared()
    {
        return (bool) $this->get('clear');
    }

    public function isProblem()
    {
        // TODO: OK is not a problem.
        $ok = [
            'notice',
            'informational',
            'debug',
        ];

        return ! \in_array($this->get('severity'), $ok);
    }
}
