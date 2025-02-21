<?php

namespace Icinga\Module\Eventtracker\Engine\Downtime;

use DateTimeImmutable;
use DateTimeInterface;
use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;
use Exception;
use Icinga\Module\Eventtracker\Engine\TimePeriod\TimePeriodDefinition;
use Icinga\Module\Eventtracker\Engine\TimePeriod\TimePeriodDefinitionFactory;
use Icinga\Module\Eventtracker\Engine\TimePeriod\TimeSlot;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

class DowntimePeriodRunner implements EventEmitterInterface
{
    use EventEmitterTrait;

    /**
     * A calculated time slot for this a period definition has been activated
     */
    public const ON_PERIOD_ACTIVATED = 'activated';

    /**
     * The current calculated time slot for this a period definition has been deactivated
     */
    public const ON_PERIOD_DEACTIVATED = 'deactivated';

    /**
     * No more future activations are to be expexcted for this time period definition
     */
    public const ON_DEFINITION_EXPIRED = 'expired';

    protected LoggerInterface $logger;
    protected ?DateTimeInterface $now = null;
    /** @var array<string, DowntimeRule> */
    protected array $currentRules = [];
    /** @var array<string, TimePeriodDefinition> */
    protected array $timePeriodDefinitions = [];
    /** @var array<string, TimeSlot> */
    protected array $activeTimeSlots = [];
    protected static ?DateTimeInterface $fakeNow = null;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param array<string, DowntimeRule> $rules Downtime Rules, indexed by config_uuid
     */
    public function setRules(array $rules): void
    {
        $new = [];
        $removed = [];
        foreach ($rules as $configUuid => $rule) {
            if (isset($this->currentRules[$configUuid])) {
                continue;
            }
            // Hint: disabled rules will never be activated, and when they're being
            // activated, their config_uuid changes. So there is no need to compare
            // them or to deal with them in any way
            if ($rule->isEnabled()) {
                $new[$configUuid] = $rule;
            }
        }

        foreach ($this->currentRules as $configUuid => $rule) {
            if (! isset($rules[$configUuid])) {
                $removed[$configUuid] = $rule;
            }
        }

        $now = self::$fakeNow ?? new DateTimeImmutable();
        $this->currentRules = [];
        foreach ($new as $configUuid => $rule) {
            $this->addRule($configUuid, $rule, $now);
        }
        foreach ($removed as $configUuid => $rule) {
            $this->removeRule($configUuid, $rule);
        }

        $this->recheckDefinitions($now);
    }

    /**
     * For testing purposes only
     *
     * @internal
     */
    public static function setFakeNow(?DateTimeInterface $now): void
    {
        self::$fakeNow = $now;
    }

    protected function recheckDefinitions(DateTimeInterface $now)
    {
        /** @var array<string, TimeSlot> $current */
        $current = $this->activeTimeSlots;
        /** @var array<string, TimeSlot> $newSlots */
        $newSlots = [];
        /** @var array<string, TimeSlot> $expiredSlots */
        $expiredSlots = [];
        /** @var array<string, TimePeriodDefinition> $expiredRules */
        $expiredRules = [];

        foreach ($this->activeTimeSlots as $id => $slot) {
            if (! $slot->isActive($now) || ! isset($this->timePeriodDefinitions[$id])) {
                $expiredSlots[$id] = $slot;
            }
        }
        foreach ($this->timePeriodDefinitions as $id => $definition) {
            if (isset($current[$id]) && !isset($expiredSlots[$id])) {
                $newSlots[$id] = $current[$id];
                continue;
            }
            if ($slot = $definition->getActiveSlot($now)) {
                unset($expiredSlots[$id]);
                $newSlots[$id] = $slot;
            } elseif (!$definition->hasSlotAfter($now)) {
                $expiredRules[$id] = $definition;
            }
        }
        $this->activeTimeSlots = $newSlots;
        foreach ($newSlots as $id => $slot) {
            $this->activatePeriod($id, $slot);
        }
        foreach ($expiredSlots as $id => $slot) {
            $this->deactivatePeriod($id, $slot);
        }
        foreach ($expiredRules as $id => $definition) {
            $this->expireDefinition($id, $this->currentRules[$id], $definition);
            $this->removeRule($id);
        }
    }

    /**
     * Decides which calculation strategy to pick for the given rule
     */
    protected function addRule(string $id, DowntimeRule $rule, DateTimeInterface $now): void
    {
        try {
            $definition = TimePeriodDefinitionFactory::createForDowntimeRule($rule);
            if ($definition->hasSlotAfter($now)) {
                $this->currentRules[$id] = $rule;
                $this->timePeriodDefinitions[$id] = $definition;
            }
        } catch (Exception $e) {
            $this->logger->error(sprintf(
                'DowntimePeriodRunner failed to activate rule "%s": %s',
                $rule->get('label'),
                $e->getMessage()
            ));
        }
    }

    protected function removeRule(string $id): void
    {
        unset($this->currentRules[$id]);
        unset($this->timePeriodDefinitions[$id]);
        unset($this->activeTimeSlots[$id]);
    }

    protected function activatePeriod(string $id, TimeSlot $slot): void
    {
        $this->emit(self::ON_PERIOD_ACTIVATED, [Uuid::fromBytes($id), $slot]);
    }

    protected function deactivatePeriod(string $id, TimeSlot $slot): void
    {
        $this->emit(self::ON_PERIOD_DEACTIVATED, [Uuid::fromBytes($id), $slot]);
    }

    protected function expireDefinition(string $id, DowntimeRule $rule, TimePeriodDefinition $definition): void
    {
        $this->emit(self::ON_DEFINITION_EXPIRED, [Uuid::fromBytes($id), $rule, $definition]);
    }
}
