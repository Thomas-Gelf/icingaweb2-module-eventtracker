<?php

namespace Icinga\Module\Eventtracker\Web\Widget;

use Icinga\Module\Eventtracker\Engine\TimePeriod\TimeSlot;
use Ramsey\Uuid\UuidInterface;

trait ActiveDowntimeSlots
{
    /** @var TimeSlot[] */
    protected array $activeTimeSlots = [];

    public function setActiveTimeSlots(array $timeSlots): self
    {
        $this->activeTimeSlots = $timeSlots;
        return $this;
    }

    protected function getActiveDowntimeSlot(UuidInterface $ruleUuid): ?TimeSlot
    {
        if ($activeSlot = $this->activeTimeSlots[$ruleUuid->toString()] ?? null) {
            return TimeSlot::fromSerialization($activeSlot);
        }

        return null;
    }
}
