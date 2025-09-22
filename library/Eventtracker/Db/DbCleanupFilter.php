<?php

namespace Icinga\Module\Eventtracker\Db;

use Icinga\Cli\Params;

class DbCleanupFilter
{
    protected ?int $timestampLimit = null;
    protected array $columnFilters = [];

    public function __construct(?int $timestampLimit, array $columnFilters)
    {
        $this->timestampLimit = $timestampLimit;
        $this->columnFilters = $columnFilters;
    }

    public function toCliParams(): array
    {
        $params = [];
        if ($this->timestampLimit === null) {
            $params[] = '--force';
        } else {
            $params[] = '--before';
            $params[] = $this->timestampLimit;
            if ($this->timestampLimit === 0) {
                $params[] = '--force';
            }
        }
        foreach ($this->columnFilters as $column => $filter) {
            $params[] = "--$column";
            $params[] = $filter;
        }

        return $params;
    }

    public static function fromCliParams(Params $params): DbCleanupFilter
    {
        $timestampLimit = null;
        $columnFilters = [];
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
                $timestampLimit = (time() - (86400 * $keepDays)) * 1000;
            }
        }
        if ($timestampLimit === null && !$force) {
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
                if (array_key_exists($key, $columnFilters)) {
                    $columnFilters[$key][] = $value;
                } else {
                    $columnFilters[$key] = [$value];
                }
            } else {
                throw new \RuntimeException("$key is not a valid filter column");
            }
        }

        return new DbCleanupFilter($timestampLimit, $columnFilters);
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
