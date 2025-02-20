<?php

namespace Icinga\Tests\Module\Eventtracker\Engine\TimePeriod;

use Icinga\Module\Eventtracker\Engine\TimePeriod\TimePeriodDefinitionNonRecurring;
use Icinga\Module\Eventtracker\Engine\TimePeriod\TimeSlot;

class TimePeriodDefinitionNonRecurringTest extends TestCase
{
    public function testSimpleRecurrenceWorks()
    {
        $definition = new TimePeriodDefinitionNonRecurring(
            $this->dateTime('2025-02-28 10:00:00'),
            null,
            $this->interval(60)
        );
        $this->assertTrue($definition->hasActiveSlot($this->dateTime('2025-02-28 10:00:00')));
        $this->assertTrue($definition->hasActiveSlot($this->dateTime('2025-02-28 10:00:59')));
        $this->assertFalse($definition->hasActiveSlot($this->dateTime('2025-03-01 10:00:59')));
        $this->assertTrue($definition->hasActiveSlot($this->dateTime('2025-02-28 10:01:00')));
        $this->assertFalse($definition->hasActiveSlot($this->dateTime('2025-02-28 10:01:01')));
    }

    public function testSimpleRecurrenceWorksWithPeriodAndHorizon()
    {
        $definition = new TimePeriodDefinitionNonRecurring(
            $this->dateTime('2025-02-28 10:00:00'),
            null,
            $this->interval(60)
        );
        $this->assertTrue($definition->hasSlotAfter($this->dateTime('2025-02-28 09:59:59')));
        $this->assertTrue($definition->hasSlotAfter($this->dateTime('2025-02-28 10:00:00')));
        $this->assertTrue($definition->hasSlotAfter($this->dateTime('2025-02-28 10:01:00')));
        $this->assertFalse($definition->hasSlotAfter($this->dateTime('2025-02-28 10:01:01')));
    }

    public function testFetchCalculationsInAGivenRangeX()
    {
        $definition = new TimePeriodDefinitionNonRecurring(
            $this->dateTime('2025-02-28 10:00:00'),
            null,
            $this->interval(60)
        );
        $this->assertEquals(
            [
                new TimeSlot($this->dateTime('2025-02-28 10:00:00'), $this->dateTime('2025-02-28 10:01:00')),
            ],
            $definition->getSlotsBetween(
                $this->dateTime('2025-02-28 09:59:59'),
                $this->dateTime('2025-02-28 10:01:01')
            )
        );
    }

    public function testFetchNoCalculations()
    {
        $definition = new TimePeriodDefinitionNonRecurring(
            $this->dateTime('2025-02-28 10:00:00'),
            null,
            $this->interval(60)
        );
        $this->assertEquals(
            [],
            $definition->getSlotsBetween(
                $this->dateTime('2025-02-28 10:02:00'),
                $this->dateTime('2025-03-28 10:03:00')
            )
        );
    }
}
