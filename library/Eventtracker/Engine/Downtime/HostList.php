<?php

namespace Icinga\Module\Eventtracker\Engine\Downtime;

use gipfl\ZfDb\Adapter\Adapter;
use Ramsey\Uuid\UuidInterface;
use RuntimeException;

// TODO: start_time, end_time
class HostList
{
    public const TABLE_NAME = 'host_list';
    public const MEMBER_TABLE_NAME = 'host_list_member';

    /** @var UuidInterface */
    protected $uuid;

    /** @var ?array */
    protected $hosts = null;

    public function __construct(UuidInterface $uuid)
    {
        $this->uuid = $uuid;
    }

    public function getHosts(): array
    {
        if ($this->hosts === null) {
            throw new RuntimeException('Hosts for this list have not been loaded');
        }

        return $this->hosts;
    }

    public static function load(UuidInterface $uuid, Adapter $db): HostList
    {
        $self = new static($uuid);
        $self->loadHosts($db);
        return $self;
    }

    public function hasHost(string $hostname): bool
    {
        if ($this->hosts === null) {
            throw new RuntimeException('Hosts for this list have not been loaded');
        }

        return isset($this->hosts[$hostname]);
    }

    public function getUuid(): UuidInterface
    {
        return $this->uuid;
    }

    protected function loadHosts(Adapter $db)
    {
        $this->hosts = $db->fetchPairs(
            $db->select()
                ->from(self::MEMBER_TABLE_NAME, [
                    'a' => 'hostname',
                    'b' => 'hostname',
                ])
                ->where('list_uuid = ?', $this->uuid->getBytes())
        );
    }
}
