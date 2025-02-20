<?php

namespace Icinga\Module\Eventtracker\Engine\TimePeriod;

use Cron\CronExpression;
use Icinga\Module\Eventtracker\Engine\Downtime\DowntimeRule;

class TimePeriodDefinitionFactory
{
    public static function createForDowntimeRule(DowntimeRule $rule)
    {
        if ($definition = $rule->get('time_definition')) {
            if (substr($definition, 0, 1) === '@') { // simple recurrence
                switch ($definition) {
                    case '@hourly':
                        $cronDefinition = sprintf(
                            '%d * * * *',
                            (int) $rule->getNotBefore()->format('i'),
                        );
                        break;
                    case '@daily':
                        $cronDefinition = sprintf(
                            '%d %d * * *',
                            (int) $rule->getNotBefore()->format('i'),
                            (int) $rule->getNotBefore()->format('H'),
                        );
                        break;
                    case '@weekly':
                        $cronDefinition = sprintf(
                            '%d %d * * %d',
                            (int) $rule->getNotBefore()->format('i'),
                            (int) $rule->getNotBefore()->format('H'),
                            (int) $rule->getNotBefore()->format('w'),
                        );
                        break;
                    case '@monthly':
                        $cronDefinition = sprintf(
                            '%d %d %d * *',
                            (int) $rule->getNotBefore()->format('i'),
                            (int) $rule->getNotBefore()->format('H'),
                            (int) $rule->getNotBefore()->format('d'),
                        );
                        break;
                    case '@yearly':
                        $cronDefinition = sprintf(
                            '%d %d %d %d *',
                            (int) $rule->getNotBefore()->format('i'),
                            (int) $rule->getNotBefore()->format('G'),
                            (int) $rule->getNotBefore()->format('d'),
                            (int) $rule->getNotBefore()->format('m'),
                        );
                        break;
                    default:
                        throw new \InvalidArgumentException("Unsupported time definition: $definition");
                }

                return new TimePeriodDefinitionCronBased(
                    new CronExpression($cronDefinition),
                    $rule->getNotBefore(),
                    $rule->getNotAfter(),
                    $rule->getDuration()
                );
            } else {
                return new TimePeriodDefinitionCronBased(
                    new CronExpression($definition),
                    $rule->getNotBefore(),
                    $rule->getNotAfter(),
                    $rule->getDuration()
                );
            }
        } else {
            return new TimePeriodDefinitionCronBased(
                new CronExpression($definition),
                $rule->getNotBefore(),
                $rule->getNotAfter(),
                $rule->getDuration()
            );
        }
    }
}
