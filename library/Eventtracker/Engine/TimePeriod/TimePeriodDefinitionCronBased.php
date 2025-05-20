<?php

namespace Icinga\Module\Eventtracker\Engine\TimePeriod;

use Cron\CronExpression;
use DateInterval;
use DateTimeInterface;

class TimePeriodDefinitionCronBased extends TimePeriodDefinitionNonRecurring
{
    protected CronExpression $cronExpression;

    public function __construct(
        CronExpression $cronExpression,
        ?DateTimeInterface $notBefore,
        ?DateTimeInterface $notAfter,
        ?DateInterval $duration
    ) {
        $this->cronExpression = $cronExpression;
        parent::__construct($notBefore, $notAfter, $duration);
    }

    #[\Override]
    public function getActiveSlot(DateTimeInterface $now): ?TimeSlot
    {
        try {
            $slot = $this->createTimeSlot($this->cronExpression->getPreviousRunDate($now, 0, true));
            if ($slot !== null && $slot->isActive($now)) {
                return $slot;
            } else {
                return null;
            }
        } catch (\Exception $e) {
            return null;
        }
    }

    #[\Override]
    public function getSlotAfter(DateTimeInterface $time, ?DateTimeInterface $horizon = null): ?TimeSlot
    {
        try {
            return $this->createTimeSlot($this->cronExpression->getNextRunDate($time));
        } catch (\Exception $e) {
            // TODO: should never happen. If in doubt: add logging
            return null;
        }
    }

    #[\Override]
    public function getSlotAtOrAfter(DateTimeInterface $time, ?DateTimeInterface $horizon = null): ?TimeSlot
    {
        try {
            return $this->createTimeSlot($this->cronExpression->getNextRunDate($time, 0, true));
        } catch (\Exception $e) {
            // TODO: should never happen. If in doubt: add logging
            return null;
        }
    }

    #[\Override]
    protected function adjustStart(DateTimeInterface $start): ?DateTimeInterface
    {
        if ($this->notBefore === null) {
            return clone $start;
        }

        return clone max($this->notBefore, $start);
    }
}
