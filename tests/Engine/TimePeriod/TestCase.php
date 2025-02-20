<?php

namespace Icinga\Tests\Module\Eventtracker\Engine\TimePeriod;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function dateTime(string $dateTime, ?string $timeZone = 'Europe/Berlin'): DateTimeImmutable
    {
        return DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dateTime, new DateTimeZone($timeZone));
    }

    protected function interval(int $seconds): DateInterval
    {
        return new DateInterval('PT' . $seconds . 'S');
    }
}
