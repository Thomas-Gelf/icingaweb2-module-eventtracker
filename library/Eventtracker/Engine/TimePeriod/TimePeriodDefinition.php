<?php

namespace Icinga\Module\Eventtracker\Engine\TimePeriod;

use DateTimeInterface;

interface TimePeriodDefinition
{
    /**
     * Whether this definition has an active slot at the given time
     */
    public function hasActiveSlot(DateTimeInterface $now): bool;

    /**
     * Get the currently active TimeSlot, null in case there is no such
     */
    public function getActiveSlot(DateTimeInterface $now): ?TimeSlot;

    /**
     * Whether this definition has at least one slot after the given time
     */
    public function hasSlotAfter(DateTimeInterface $time, ?DateTimeInterface $horizon = null): bool;

    /**
     * Get next TimeSlot starting after the given time, null in case there is no such
     */
    public function getSlotAfter(DateTimeInterface $time, ?DateTimeInterface $horizon = null): ?TimeSlot;

    /**
     * Retrieve all TimeSlots starting at or after $start and ending at or after $end,
     * but not more than $limit instances (if given)
     *
     * @return TimeSlot[]
     */
    public function getSlotsBetween(DateTimeInterface $start, DateTimeInterface $end, ?int $limit = null): array;
}
