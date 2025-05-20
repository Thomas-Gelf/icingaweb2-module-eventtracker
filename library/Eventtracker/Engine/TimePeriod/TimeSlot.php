<?php

namespace Icinga\Module\Eventtracker\Engine\TimePeriod;

use DateTimeImmutable as DTI;
use DateTimeInterface as DT;
use gipfl\Json\JsonSerialization;
use stdClass;

class TimeSlot implements JsonSerialization
{
    /** @readonly  */
    public ?DT $start;
    /** @readonly  */
    public ?DT $end;

    public function __construct(?DT $start, ?DT $end)
    {
        $this->start = $start ? clone $start : null;
        $this->end = $end ? clone $end : null;
    }

    public function isActive(DT $now): bool
    {
        return ($this->start === null || $this->start <= $now)
            && ($this->end === null || $this->end >= $now);
    }

    public function willBeActiveAfter(DT $now): bool
    {
        if ($this->end && $this->end < $now) {
            return false;
        }

        return true;
    }

    public function jsonSerialize(): stdClass
    {
        return (object) [
            'start' => $this->start ? $this->start->format(DT::RFC3339_EXTENDED) : null,
            'end'   => $this->end ? $this->end->format(DT::RFC3339_EXTENDED) : null,
        ];
    }

    public static function fromSerialization($any): TimeSlot
    {
        return new TimeSlot(
            isset($any->start) ? DTI::createFromFormat(DT::RFC3339_EXTENDED, $any->start) : null,
            isset($any->end) ? DTI::createFromFormat(DT::RFC3339_EXTENDED, $any->end) : null,
        );
    }
}
