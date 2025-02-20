<?php

namespace Icinga\Module\Eventtracker\Engine\TimePeriod;

use DateInterval;
use DateTimeInterface;

class TimePeriodDefinitionNonRecurring implements TimePeriodDefinition
{
    protected ?DateTimeInterface $notBefore;
    protected ?DateTimeInterface $notAfter;
    protected ?DateInterval $duration = null;

    public function __construct(?DateTimeInterface $notBefore, ?DateTimeInterface $notAfter, ?DateInterval $duration)
    {
        $this->notBefore = $notBefore;
        $this->notAfter = $notAfter;
        $this->duration = $duration;
    }

    public function hasActiveSlot(DateTimeInterface $now): bool
    {
        return $this->getActiveSlot($now) !== null;
    }

    public function getActiveSlot(DateTimeInterface $now): ?TimeSlot
    {
        $slot = $this->createTimeSlot($now);
        return $slot->isActive($now) ? $slot : null;
    }

    public function hasSlotAfter(DateTimeInterface $time, ?DateTimeInterface $horizon = null): bool
    {
        return $this->getSlotAfter($time, $horizon) !== null;
    }

    public function getSlotAfter(DateTimeInterface $time, ?DateTimeInterface $horizon = null): ?TimeSlot
    {
        $slot = $this->createTimeSlot($time);
        if ($slot && $horizon && $slot->start && $slot->start > $horizon) {
            return null;
        }

        if ($slot && $slot->willBeActiveAfter($time)) {
            return $slot;
        }

        return null;
    }

    public function getSlotAtOrAfter(DateTimeInterface $time, ?DateTimeInterface $horizon = null): ?TimeSlot
    {
        $slot = $this->createTimeSlot($time);
        if ($slot && $horizon && $slot->start && $slot->start > $horizon) {
            return null;
        }

        if ($slot && $slot->willBeActiveAfter($time)) {
            return $slot;
        }

        return null;
    }

    public function getSlotsBetween(DateTimeInterface $start, DateTimeInterface $end, ?int $limit = null): array
    {
        $slots = [];
        $cnt = 0;
        while ($slot = $this->getSlotAtOrAfter($start, $end)) {
            if ($slot->end > $end) {
                break;
            }
            if ($limit !== null && $cnt >= $limit) {
                break;
            }
            $slots[] = $slot;
            $cnt++;
            if ($slot->end === null) {
                break;
            }
            $start = clone $slot->end;
            $start = $start->add(new DateInterval('PT1S'));
        }

        return $slots;
    }

    protected function adjustStart(DateTimeInterface $start): ?DateTimeInterface
    {
        if ($this->notBefore === null) {
            return null;
        }

        return clone $this->notBefore;
    }

    protected function createTimeSlot(DateTimeInterface $now): ?TimeSlot
    {
        return $this->prepareTimeSlotForStart($this->adjustStart($now));
    }

    protected function prepareTimeSlotForStart(DateTimeInterface $start): ?TimeSlot
    {
        if ($this->duration === null) {
            $end = clone $this->notAfter;
        } else {
            $end = clone $start;
            $end = $end->add($this->duration);
            if ($this->notAfter !== null) {
                $end = clone min($this->notAfter, $end);
            }
        }

        if ($end !== null && $end <= $start) {
            return null;
        }

        return new TimeSlot($start, $end);
    }
}
