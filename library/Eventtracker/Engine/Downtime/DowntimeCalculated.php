<?php

namespace Icinga\Module\Eventtracker\Engine\Downtime;

use DateTime;
use DateTimeInterface;
use gipfl\Json\JsonSerialization;
use gipfl\ZfDbStore\DbStorableInterface;
use Ramsey\Uuid\Uuid;

class DowntimeCalculated implements JsonSerialization, DbStorableInterface
{
    use UuidObjectHelper;

    public const TABLE_NAME = 'downtime_calculated';
    public const TS_NEVER = 2145916800000; // 2038-01-01

    protected $tableName = self::TABLE_NAME;
    protected $keyProperty = 'uuid';
    protected $defaultProperties = [
        'uuid'              => null,
        'rule_uuid'         => null,
        'rule_config_uuid'  => null,
        'ts_expected_start' => null,
        'ts_expected_end'   => null,
        'is_active'         => null,
        'ts_started'        => null,
        'ts_triggered'      => null,
    ];

    public static function createCalculated(DowntimeRule $rule, DateTimeInterface $expectedStart): DowntimeCalculated
    {
        $start = (int) floor((int) $expectedStart->format('Uu') / 1000);
        if ($duration = $rule->get('duration')) {
            $end = $start + $duration * 1000;
        } else {
            $end = self::TS_NEVER; // must NOT be NULL
        }

        return (new static())->setProperties([
            'uuid' => Uuid::uuid5(
                Uuid::fromBytes($rule->get('uuid')),
                $rule->get('config_uuid') . $start
            )->getBytes(),
            'rule_uuid'         => $rule->get('uuid'),
            'rule_config_uuid'  => $rule->get('config_uuid'),
            'ts_expected_start' => $start,
            'ts_expected_end'   => $end,
            'is_active'         => 'n'
        ]);
    }

    public function getExpectedStart(): DateTimeInterface
    {
        return static::tsToDateTime($this->get('ts_expected_start'));
    }

    public function getExpectedEnd(): ?DateTimeInterface
    {
        $end = $this->get('ts_expected_end');
        if ($end === null) {
            return null;
        }

        return static::tsToDateTime($end);
    }

    protected static function tsToDateTime($ts): DateTimeInterface
    {
        return new DateTime('@' . floor($ts / 1000));
    }
}
