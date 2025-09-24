<?php

namespace Icinga\Module\Eventtracker\Engine\Downtime;

use DateTimeImmutable;
use DateTimeInterface;
use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;
use Exception;
use gipfl\ZfDb\Adapter\Adapter;
use Icinga\Module\Eventtracker\Daemon\DbBasedComponent;
use Icinga\Module\Eventtracker\Daemon\SimpleDbBasedComponent;
use Icinga\Module\Eventtracker\Engine\TimePeriod\TimePeriodDefinition;
use Icinga\Module\Eventtracker\Engine\TimePeriod\TimePeriodDefinitionFactory;
use Icinga\Module\Eventtracker\Engine\TimePeriod\TimeSlot;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use React\EventLoop\Loop;
use React\EventLoop\TimerInterface;

class DowntimePeriodRunner implements EventEmitterInterface, DbBasedComponent
{
    use EventEmitterTrait;
    use SimpleDbBasedComponent;

    /**
     * A calculated time slot for this a period definition has been activated
     */
    public const ON_PERIOD_ACTIVATED = 'activated';

    /**
     * The current calculated time slot for this a period definition has been deactivated
     */
    public const ON_PERIOD_DEACTIVATED = 'deactivated';

    /**
     * No more future activations are to be expected for this time period definition
     */
    public const ON_DEFINITION_EXPIRED = 'expired';

    public const ON_PERIODS_CHANGED = 'changed';

    protected LoggerInterface $logger;
    protected ?DateTimeInterface $now = null;
    /** @var array<string, DowntimeRule> */
    protected array $currentRules = [];
    /** @var array<string, DowntimeRule> */
    protected array $activeRules = [];
    /** @var array<string, TimePeriodDefinition> */
    protected array $timePeriodDefinitions = [];
    /** @var array<string, TimeSlot> */
    protected array $activeTimeSlots = [];
    protected static ?DateTimeInterface $fakeNow = null;
    protected ?TimerInterface $periodicRecheck = null;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->periodicRecheck = Loop::addPeriodicTimer(30, fn () => $this->recheckDefinitions());
    }

    public function hasActiveRules(): bool
    {
        return !empty($this->activeRules);
    }

    /**
     * @return array<string, DowntimeRule>
     */
    public function getActiveRules(): array
    {
        // $this->logger->notice(var_export($this->activeRules, 1));
        return $this->activeRules;
    }

    /**
     * @return array<string, TimeSlot>
     */
    public function getActiveTimeSlots(): array
    {
        return $this->activeTimeSlots;
    }

    protected function onDbReady(): void
    {
        $this->recheckDowntimeRules();
    }

    public function recheckDowntimeRules(): void
    {
        if ($db = $this->db) {
            $this->setRules($this->fetchDowntimeRules($db));
        } else {
            $this->logger->notice('I should recheck Downtime rules, but I have no DB');
        }
    }

    public function getRuleByUuid(UuidInterface $uuid): ?DowntimeRule
    {
        return $this->currentRules[$uuid->toString()] ?? null;
    }

    public function getRuleByConfigUuid(UuidInterface $uuid): ?DowntimeRule
    {
        $binaryUuid = $uuid->getBytes();
        foreach ($this->currentRules as $rule) {
            if ($rule->get('config_uuid') === $binaryUuid) {
                return $rule;
            }
        }

        return null;
    }

    /**
     * @return DowntimeRule[]
     */
    protected function fetchDowntimeRules(Adapter $db): array
    {
        $rules = [];
        foreach ($db->fetchAll($db->select()->from(DowntimeRule::TABLE_NAME)) as $row) {
            $rule = DowntimeRule::fromSerialization((object) $row);
            $rule->setStored();
            $rules[Uuid::fromBytes($row->uuid)->toString()] = $rule;
        }

        return $rules;
    }

    /**
     * @param array<string, DowntimeRule> $rules Downtime Rules, indexed by human-readable uuid
     */
    public function setRules(array $rules): void
    {
        // $this->logger->notice('Setting downtime rules');
        $new = [];
        $removed = [];
        foreach ($rules as $uuid => $rule) {
            if (isset($this->currentRules[$uuid])) {
                continue;
            }
            // Hint: disabled rules will never be activated, and when they're being
            // activated, their config_uuid changes. So there is no need to compare
            // them or to deal with them in any way
            if ($rule->isEnabled()) {
                $new[$uuid] = $rule;
            }
        }

        foreach ($this->currentRules as $uuid => $rule) {
            // Instead of checking for 'enabled', we might want to fetch only enabled ones
            if (! isset($rules[$uuid]) || ! $rules[$uuid]->isEnabled()) {
                $removed[$uuid] = $rule;
            }
        }

        $now = self::now();
        // $this->currentRules = [];
        foreach ($new as $uuid => $rule) {
            $this->addRule($uuid, $rule, $now);
        }
        foreach ($removed as $uuid => $rule) {
            $this->removeRule($uuid, $rule);
        }

        $this->recheckDefinitions($now);
    }

    protected function getNextExpectedChange(): ?DateTimeInterface
    {
        $candidates = [];
        foreach ($this->activeTimeSlots as $slot) {
            if ($slot->end) {
                $candidates[] = $slot->end;
            }
        }

        $now = self::now();
        foreach ($this->timePeriodDefinitions as $id => $definition) {
            if (! isset($this->activeTimeSlots[$id])) {
                if ($next = $definition->getSlotAfter($now)) {
                    $candidates[] = $next->start;
                }
            }
        }

        if (empty($candidates)) {
            return null;
        }

        return min($candidates);
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

    protected function recheckDefinitions(?DateTimeInterface $now = null): bool
    {
        $now ??= self::now();
        // $this->logger->notice('RE-Checking Downtime rule definitions');
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
        // $this->activeTimeSlots = $newSlots;
        foreach ($newSlots as $id => $slot) {
            $this->activatePeriod($id, $slot);
        }
        foreach ($expiredSlots as $id => $slot) {
            $this->deactivatePeriod($id, $slot);
        }
        foreach ($expiredRules as $id => $definition) {
            $this->expireDefinition($id, $definition);
            $this->removeRule($id);
        }

        $changed = !empty($newSlots) || !empty($expiredSlots) || !empty($expiredRules);
        if ($changed) {
            $this->emit(self::ON_PERIODS_CHANGED);
            if ($nextCheck = $this->getNextExpectedChange()) {
                $diff = $nextCheck->getTimestamp() - (new DateTimeImmutable())->getTimestamp();
                if (self::$fakeNow === null && $diff > 0) {
                    Loop::addTimer($diff + 0.1, fn () => $this->recheckDefinitions());
                }
            }
        }

        return $changed;
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
                // $this->logger->notice('Activated definition ' . $rule->get('label'));
                // else: $this->logger->notice('Definition has no slot after now: ' . var_export($rule, true));
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
        unset($this->activeRules[$id]);
        unset($this->timePeriodDefinitions[$id]);
        unset($this->activeTimeSlots[$id]);
    }

    protected function activatePeriod(string $id, TimeSlot $slot): void
    {
        if ($alreadyActive = ($this->activeTimeSlots[$id] ?? null)) {
            if ($alreadyActive->start === $slot->start && $alreadyActive->end === $slot->end) {
                return;
            }
        }
        $this->activeTimeSlots[$id] = $slot;
        $this->activeRules[$id] = $this->currentRules[$id];
        // $this->logger->notice("Activated period $id");
        $this->emit(self::ON_PERIOD_ACTIVATED, [Uuid::fromString($id), $slot]);
    }

    protected function deactivatePeriod(string $id, TimeSlot $slot): void
    {
        // $this->logger->notice("Deactivated period $id");
        unset($this->activeTimeSlots[$id]);
        unset($this->activeRules[$id]);

        $this->emit(self::ON_PERIOD_DEACTIVATED, [Uuid::fromString($id), $slot]);
    }

    protected function expireDefinition(string $id, TimePeriodDefinition $definition): void
    {
        // $this->logger->notice("Expired definition $id");
        if (isset($this->activeRules[$id])) {
            $rule = $this->activeRules[$id];
            $this->deactivatePeriod($id, $this->activeTimeSlots[$id]);
        } else {
            $rule = null;
        }
        unset($this->currentRules[$id]);
        if ($rule) {
            $this->emit(self::ON_DEFINITION_EXPIRED, [Uuid::fromString($id), $rule, $definition]);
        }
    }

    public function stop(): void
    {
        if ($this->periodicRecheck) {
            Loop::cancelTimer($this->periodicRecheck);
            $this->periodicRecheck = null;
        }
    }

    protected static function now(): DateTimeInterface
    {
        return self::$fakeNow ?? new DateTimeImmutable();
    }

    public function __destruct()
    {
        // Doesn't seem to trigger as long as the timer is active
        $this->stop();
    }
}
