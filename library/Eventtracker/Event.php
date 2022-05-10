<?php

namespace Icinga\Module\Eventtracker;

use InvalidArgumentException;
use Ramsey\Uuid\Uuid;

use function ipl\Stdlib\get_php_type;

class Event
{
    use PropertyHelpers {
        set as setProperty;
    }

    /** @var string */
    const FILES_PROPERTY = 'files';

    /** @var FrozenMemoryFile[] */
    protected $files = [];

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

    public function getFiles(): array
    {
        return $this->files;
    }

    public function set($key, $value)
    {
        if ($key === static::FILES_PROPERTY) {
            if (! is_array($value)) {
                throw new InvalidArgumentException(sprintf(
                    'Expected array for property %s, got %s instead',
                    static::FILES_PROPERTY,
                    get_php_type($value)
                ));
            }

            foreach ($value as $pos => $spec) {
                foreach (['name', 'data'] as $requiredKey) {
                    if (! isset($spec->$requiredKey)) {
                        throw new InvalidArgumentException(sprintf(
                            'key "%s" expected for file at position %s',
                            $requiredKey,
                            // $pos is intentionally treated as a string,
                            // since senders can provide a key even if it's useless and %d would then fail
                            $pos
                        ));
                    }
                }

                if (substr($spec->data, 0, 7) === 'base64,') {
                    $file = FrozenMemoryFile::fromBase64($spec->name, substr($spec->data, 7));
                } else {
                    $file = FrozenMemoryFile::fromBinary($spec->name, $spec->data);
                }

                $this->files[] = $file;
            }

            return $this;
        }

        return $this->setProperty($key, $value);
    }
}
