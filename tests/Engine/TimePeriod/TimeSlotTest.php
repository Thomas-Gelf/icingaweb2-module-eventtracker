<?php

namespace Icinga\Tests\Module\Eventtracker\Engine\TimePeriod;

use Icinga\Module\Eventtracker\Engine\TimePeriod\TimeSlot;

class TimeSlotTest extends TestCase
{
    public function testNormalTimeSlotKnowsWhenItIsActive()
    {
        $slot = new TimeSlot($this->dateTime('2025-02-28 10:00:00'), $this->dateTime('2025-03-01 10:00:00'));
        $this->assertFalse($slot->isActive($this->dateTime('2125-02-28 10:00:00')));
        $this->assertTrue($slot->isActive($this->dateTime('2025-02-28 11:00:00')));
        $this->assertFalse($slot->isActive($this->dateTime('2025-02-28 09:59:59')));
    }

    public function testWorksWithIllFormattedDates()
    {
        $slot = new TimeSlot($this->dateTime('2025-02-28 10:00:00'), $this->dateTime('2025-03-01 10:00:00'));
        $this->assertTrue($slot->isActive($this->dateTime('2025-02-28 09:59:60')));
    }

    public function testWorksAsExpectedForEdgeValues()
    {
        $slot = new TimeSlot($this->dateTime('2025-02-28 10:00:00'), $this->dateTime('2025-03-01 10:00:00'));
        $this->assertTrue($slot->isActive($this->dateTime('2025-02-28 10:00:00')));
        $this->assertTrue($slot->isActive($this->dateTime('2025-03-01 10:00:00')));
        $this->assertFalse($slot->isActive($this->dateTime('2025-03-01 10:00:01')));
    }

    public function testTimeSlotWithNoKnowsWhenItIsActiveEnd()
    {
        $slot = new TimeSlot($this->dateTime('2025-02-28 10:00:00'), null);
        $this->assertTrue($slot->isActive($this->dateTime('2125-02-28 10:00:00')));
        $this->assertTrue($slot->isActive($this->dateTime('2025-02-28 10:00:00')));
        $this->assertFalse($slot->isActive($this->dateTime('2025-02-28 09:59:59')));
    }
}
