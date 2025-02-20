<?php

namespace Icinga\Module\Eventtracker\Engine\TimePeriod;

use DateTimeInterface;

class TimeSlot
{
    /** @readonly  */
    public ?DateTimeInterface $start;
    /** @readonly  */
    public ?DateTimeInterface $end;

    public function __construct(?DateTimeInterface $start, ?DateTimeInterface $end)
    {
        $this->start = $start ? clone $start : null;
        $this->end = $end ? clone $end : null;
    }

    public function isActive(DateTimeInterface $now): bool
    {
        return ($this->start === null || $this->start <= $now)
            && ($this->end === null || $this->end >= $now);
    }

    public function willBeActiveAfter(DateTimeInterface $now): bool
    {
        if ($this->end && $this->end < $now) {
            return false;
        }

        return true;
    }
}
