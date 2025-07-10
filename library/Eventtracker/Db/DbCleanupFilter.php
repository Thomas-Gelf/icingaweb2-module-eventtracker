<?php

namespace Icinga\Module\Eventtracker\Db;

use Icinga\Cli\Params;

class DbCleanupFilter
{
    protected ?int $timestampLimit = null;
    protected array $columnFilters = [];

    protected function __construct()
    {
    }

    public static function fromCliParams(Params $params): DbCleanupFilter
    {
        $self = new DbCleanupFilter();
        $params = clone $params;
        $force = (bool) $params->shift('force');
        if ($before = $params->shift('before')) {
            throw new \RuntimeException('--before has not yet been implemented');
        }
        if ($keepDays = $params->shift('keep-days')) {
            if (! ctype_digit($keepDays)) {
                throw new \RuntimeException('--keep-days must be a positive number, got ' . $keepDays);
            }
            $keepDays = (int) $keepDays;
            if ($keepDays > 0) {
                $self->timestampLimit = (time() - (86400 * $keepDays)) * 1000;
            }
        }
        if ($self->timestampLimit === null && !$force) {
            throw new \RuntimeException('Got no time constraint, and --force has not been used');
        }
        // They should not be there. To be on the safe side, but we shift them anyway,
        $params->shift('benchmark');
        $params->shift('debug');
        $params->shift('verbose');
        $params->shift('trace');

        $validFilters = ['host_name', 'object_name', 'object_class'];
        foreach ($params->getParams() as $key => $value) {
            if (in_array($key, $validFilters)) {
                if (array_key_exists($key, $self->columnFilters)) {
                    $self->columnFilters[$key][] = $value;
                } else {
                    $self->columnFilters[$key] = [$value];
                }
            } else {
                throw new \RuntimeException("$key is not a valid filter column");
            }
        }

        return $self;
    }

    public function getColumnFilters(): array
    {
        return $this->columnFilters;
    }

    public function hasTimeConstraint(): bool
    {
        return $this->timestampLimit !== null;
    }

    public function getTimestampLimit(): int
    {
        if ($this->timestampLimit === null) {
            throw new \RuntimeException('DbCleanupFilter usage asks for timestamp limit, but there is no such');
        }

        return $this->timestampLimit;
    }
}
