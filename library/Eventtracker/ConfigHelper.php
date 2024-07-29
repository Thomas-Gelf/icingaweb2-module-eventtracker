<?php

namespace Icinga\Module\Eventtracker;

use gipfl\ZfDb\Adapter\Adapter as ZfDb;
use Icinga\Module\Icingadb\Model\CustomvarFlat;
use Icinga\Module\Icingadb\Model\Host as IcingaDbHost;
use Icinga\Module\Monitoring\Object\Host;
use Icinga\Module\Monitoring\Object\MonitoredObject;
use ipl\Orm\Model;

class ConfigHelper
{
    public static function getList($value)
    {
        if ($value !== null && \strlen($value) > 0) {
            return \preg_split('/\s*,\s*/', $value, -1, \PREG_SPLIT_NO_EMPTY);
        } else {
            return [];
        }
    }

    // has been moved to ConfigHelperCi, remains here for iET Compat
    public static function fillPlaceHoldersForIssue($string, Issue $issue, ZfDb $db)
    {
        return ConfigHelperCi::fillPlaceHoldersForIssue($string, $issue, $db);
    }

    public static function missingProperty($property, bool $missingIsNull = false)
    {
        if ($missingIsNull) {
            return null;
        }

        return '{' . $property . '}';
    }

    public static function getIssueProperty(Issue $issue, $property)
    {
        if ($property === 'uuid') {
            return $issue->getNiceUuid();
        }

        if (\preg_match('/^attributes\.(.+)$/', $property, $match)) {
            return $issue->getAttribute($match[1]);
        }

        // TODO: check whether Issue has such property, and eventually use an interface
        if ($issue->hasProperty($property)) {
            $value = $issue->get($property);
            if ($value === null) {
                // return missing property? Not sure
            }

            return $value;
        }

        return static::missingProperty($property);
    }

    protected static function icingaHostStateMap($numeric)
    {
        switch ((int) $numeric) {
            case 0:
                return 'UP';
            case 1:
                return 'DOWN';
            case 2:
                return 'UNREACHABLE';
            case 99:
                return 'PENDING';
            default:
                return $numeric;
        }
    }

    protected static function icingaServiceStateMap($numeric)
    {
        switch ((int) $numeric) {
            case 0:
                return 'OK';
            case 1:
                return 'WARNING';
            case 2:
                return 'CRITICAL';
            case 3:
                return 'UNKNOWN';
            case 99:
                return 'PENDING';
            default:
                return $numeric;
        }
    }

    /**
     * @param $string
     * @param Event|Issue|object $issue
     * @param callable|null $callback
     * @return string|null
     */
    public static function fillPlaceholders($string, $issue, callable $callback = null, bool $missingIsNull = false)
    {
        $replace = function ($match) use ($issue, $missingIsNull) {
            $property = \trim($match[1], '{}');
            list($property, $modifier) = static::extractPropertyModifier($property);
            if ($issue instanceof Issue) {
                $value = static::getIssueProperty($issue, $property);
            } elseif ($issue instanceof Event) {
                // TODO: check whether Event has such property, and eventually use an interface
                $value = $issue->get($property);
            } elseif ($issue instanceof MonitoredObject) {
                if (preg_match('/^(host|service)\.vars\.([^.]+)$/', $property, $pMatch)) {
                    $value = $issue->{'_' . $pMatch[1] . '_' . $pMatch[2]};
                } elseif (preg_match('/^vars\.([^.]+)$/', $property, $pMatch)) {
                    if ($issue instanceof Host) {
                        $value = $issue->{'_host_' . $pMatch[1]};
                    } else {
                        $value = $issue->{'_service_' . $pMatch[1]};
                        if ($value === null) {
                            $value = $issue->{'_host_' . $pMatch[1]};
                        }
                    }
                } else {
                    if ($property === 'state') {
                        if ($issue instanceof Host) {
                            $value = self::icingaHostStateMap($issue->state);
                        } else {
                            $value = self::icingaServiceStateMap($issue->state);
                        }
                    } else {
                        try {
                            $value = $issue->$property;
                        } catch (\Exception $e) {
                            $value = null;
                        }
                    }
                }
            } elseif ($issue instanceof Model) {
                if ($property === 'state') {
                    $value = strtoupper($issue->state->getStateText());
                } elseif ($property === 'host_name') {
                    if ($issue instanceof IcingaDbHost) {
                        $value = $issue->name;
                    } else {
                        $value = $issue->host->name;
                    }
                } elseif (in_array($property, ['service_name', 'service_description'])) {
                    if ($issue instanceof IcingaDbHost) {
                        $value = null;
                    } else {
                        $value = $issue->name;
                    }
                } elseif (in_array($property, ['output', 'long_output'])) {
                    $value = $issue->state->$property;
                } elseif (preg_match('/^(host|service)\.vars\.([^.]+)$/', $property, $pMatch)) {
                    if ($issue instanceof IcingaDbHost) {
                        if ($pMatch[1] === 'service') {
                            $value = null;
                        } else {
                            $vars = (new CustomvarFlat())->unFlattenVars($issue->customvar_flat);
                            $value = $vars[$pMatch[2]] ?? null;
                        }
                    } else {
                        if ($pMatch[1] === 'service') {
                            $vars = (new CustomvarFlat())->unFlattenVars($issue->customvar_flat);
                            $value = $vars[$pMatch[2]] ?? null;
                        } else {
                            $vars = (new CustomvarFlat())->unFlattenVars($issue->host->customvar_flat);
                            $value = $vars[$pMatch[2]] ?? null;
                        }
                    }
                } elseif (preg_match('/^vars\.([^.]+)$/', $property, $pMatch)) {
                    if ($issue instanceof IcingaDbHost) {
                        $vars = (new CustomvarFlat())->unFlattenVars($issue->customvar_flat);
                        $value = $vars[$pMatch[1]] ?? null;
                    } else {
                        $vars = (new CustomvarFlat())->unFlattenVars($issue->customvar_flat);
                        $value = $vars[$pMatch[1]] ?? null;
                        if ($value === null) {
                            $vars = (new CustomvarFlat())->unFlattenVars($issue->host->customvar_flat);
                            $value = $vars[$pMatch[1]] ?? null;
                        }
                    }
                } else {
                    try {
                        $value = $issue->$property ?? null;
                    } catch (\Exception $e) {
                        $value = null;
                    }
                }
            } else {
                try {
                    $value = $issue->$property ?? null;
                } catch (\Exception $e) {
                    $value = null;
                }
            }
            if ($value === null) {
                return static::missingProperty($property, $missingIsNull);
            }
            if (! is_string($value)) {
                throw new \RuntimeException(sprintf(
                    'String value expected, got %s for %s',
                    $property,
                    get_debug_type($value)
                ));
            }

            static::applyPropertyModifier($value, $modifier);

            return $value;
        };

        if ($callback !== null) {
            $_replace = $replace;
            $replace = function ($match) use ($callback, $_replace) {
                $value = $_replace($match);

                return $callback($value);
            };
        }

        return \preg_replace_callback('/({[^}]+})/', $replace, $string);
    }

    public static function applyPropertyModifier(&$value, $modifier, ?ZfDb $db = null)
    {
        // Hint: $modifier could be null
        switch ($modifier) {
            case 'lower':
                $value = \strtolower($value);
                break;
            case 'stripTags':
                $value = \strip_tags($value);
                break;
            case 'problemHandlingUrl':
                if ($db !== null && $value !== null) {
                    try {
                        if ($link = $db->fetchOne(
                            $db->select()->from('problem_handling', 'instruction_url')->where('label = ?', $value)
                        )) {
                            $value = $link;
                        }
                    } catch (\Exception $e) {
                        $value = null;
                    }
                } else {
                    $value = null;
                }

                break;
        }
    }

    public static function extractPropertyModifier(string $property): array
    {
        $modifier = null;
        // TODO: make property modifiers dynamic
        if (\preg_match('/:lower$/', $property)) {
            $property = \preg_replace('/:lower$/', '', $property);
            $modifier = 'lower';
        }
        if (\preg_match('/:stripTags$/', $property)) {
            $property = \preg_replace('/:stripTags$/', '', $property);
            $modifier = 'stripTags';
        }
        if (\preg_match('/:problemHandlingUrl$/', $property)) {
            $property = \preg_replace('/:problemHandlingUrl$/', '', $property);
            $modifier = 'problemHandlingUrl';
        }

        return [$property, $modifier];
    }
}
