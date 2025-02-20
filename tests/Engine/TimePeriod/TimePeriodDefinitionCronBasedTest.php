<?php

namespace Icinga\Tests\Module\Eventtracker\Engine\TimePeriod;

use Cron\CronExpression;
use Icinga\Module\Eventtracker\Engine\TimePeriod\TimePeriodDefinitionCronBased;
use Icinga\Module\Eventtracker\Engine\TimePeriod\TimeSlot;

class TimePeriodDefinitionCronBasedTest extends TestCase
{
    public function testSimpleCronExpressionWorksFine()
    {
        $definition = new TimePeriodDefinitionCronBased(
            new CronExpression('0,5,10,15,20,25,30,35,40,45,50,55 * * * *'),
            null,
            null,
            $this->interval(180)
        );

        $this->assertTrue($definition->hasActiveSlot($this->dateTime('2025-02-28 09:57:59')));
        $this->assertFalse($definition->hasActiveSlot($this->dateTime('2025-02-28 09:59:59')));
        $this->assertTrue($definition->hasActiveSlot($this->dateTime('2025-02-28 10:00:00')));
        $this->assertTrue($definition->hasActiveSlot($this->dateTime('2025-02-28 10:02:00')));
        $this->assertFalse($definition->hasActiveSlot($this->dateTime('2025-02-28 10:04:00')));
        $this->assertTrue($definition->hasActiveSlot($this->dateTime('2025-02-28 10:05:00')));
        $this->assertTrue($definition->hasActiveSlot($this->dateTime('2025-02-28 10:08:00')));
        $this->assertFalse($definition->hasActiveSlot($this->dateTime('2025-02-28 10:08:01')));
        $this->assertTrue($definition->hasActiveSlot($this->dateTime('2025-02-28 10:11:00')));
        $this->assertTrue($definition->hasActiveSlot($this->dateTime('2025-02-28 10:11:01')));
    }

    public function testTimeConstrainedCronExpressionWorksFine()
    {
        $definition = new TimePeriodDefinitionCronBased(
            new CronExpression('0,5,10,15,20,25,30,35,40,45,50,55 * * * *'),
            $this->dateTime('2025-02-28 10:00:00'),
            $this->dateTime('2025-02-28 10:11:00'),
            $this->interval(180)
        );

        $this->assertFalse($definition->hasActiveSlot($this->dateTime('2025-02-28 09:57:59')));
        $this->assertTrue($definition->hasActiveSlot($this->dateTime('2025-02-28 10:00:00')));
        $this->assertTrue($definition->hasActiveSlot($this->dateTime('2025-02-28 10:02:00')));
        $this->assertFalse($definition->hasActiveSlot($this->dateTime('2025-02-28 10:04:00')));
        $this->assertTrue($definition->hasActiveSlot($this->dateTime('2025-02-28 10:05:00')));
        $this->assertTrue($definition->hasActiveSlot($this->dateTime('2025-02-28 10:08:00')));
        $this->assertFalse($definition->hasActiveSlot($this->dateTime('2025-02-28 10:08:01')));
        $this->assertTrue($definition->hasActiveSlot($this->dateTime('2025-02-28 10:11:00')));
        $this->assertFalse($definition->hasActiveSlot($this->dateTime('2025-02-28 10:11:01')));
    }

    public function testFetchCalculationsInAGivenRange()
    {
        $definition = new TimePeriodDefinitionCronBased(
            new CronExpression('0,5,10,15,20,25,30,35,40,45,50,55 * * * *'),
            null,
            null,
            $this->interval(180)
        );
        $this->assertEquals(
            [
                new TimeSlot($this->dateTime('2025-02-28 10:00:00'), $this->dateTime('2025-02-28 10:03:00')),
                new TimeSlot($this->dateTime('2025-02-28 10:05:00'), $this->dateTime('2025-02-28 10:08:00')),
                new TimeSlot($this->dateTime('2025-02-28 10:10:00'), $this->dateTime('2025-02-28 10:13:00')),
                new TimeSlot($this->dateTime('2025-02-28 10:15:00'), $this->dateTime('2025-02-28 10:18:00')),
                new TimeSlot($this->dateTime('2025-02-28 10:20:00'), $this->dateTime('2025-02-28 10:23:00')),
                new TimeSlot($this->dateTime('2025-02-28 10:25:00'), $this->dateTime('2025-02-28 10:28:00')),
                new TimeSlot($this->dateTime('2025-02-28 10:30:00'), $this->dateTime('2025-02-28 10:33:00')),
                new TimeSlot($this->dateTime('2025-02-28 10:35:00'), $this->dateTime('2025-02-28 10:38:00')),
                new TimeSlot($this->dateTime('2025-02-28 10:40:00'), $this->dateTime('2025-02-28 10:43:00')),
                new TimeSlot($this->dateTime('2025-02-28 10:45:00'), $this->dateTime('2025-02-28 10:48:00')),
                new TimeSlot($this->dateTime('2025-02-28 10:50:00'), $this->dateTime('2025-02-28 10:53:00')),
                new TimeSlot($this->dateTime('2025-02-28 10:55:00'), $this->dateTime('2025-02-28 10:58:00')),
                new TimeSlot($this->dateTime('2025-02-28 11:00:00'), $this->dateTime('2025-02-28 11:03:00')),
                new TimeSlot($this->dateTime('2025-02-28 11:05:00'), $this->dateTime('2025-02-28 11:08:00')),
            ],
            $definition->getSlotsBetween(
                $this->dateTime('2025-02-28 10:00:00'),
                $this->dateTime('2025-02-28 11:09:59')
            )
        );
    }

    public function testFetchLimitedAmountCalculationsInAGivenRange()
    {
        $definition = new TimePeriodDefinitionCronBased(
            new CronExpression('0,5,10,15,20,25,30,35,40,45,50,55 * * * *'),
            null,
            null,
            $this->interval(180)
        );
        $this->assertEquals(
            [
                new TimeSlot($this->dateTime('2025-02-28 10:00:00'), $this->dateTime('2025-02-28 10:03:00')),
                new TimeSlot($this->dateTime('2025-02-28 10:05:00'), $this->dateTime('2025-02-28 10:08:00')),
                new TimeSlot($this->dateTime('2025-02-28 10:10:00'), $this->dateTime('2025-02-28 10:13:00')),
            ],
            $definition->getSlotsBetween(
                $this->dateTime('2025-02-28 10:00:00'),
                $this->dateTime('2025-02-28 11:09:59'),
                3
            )
        );
    }
}
