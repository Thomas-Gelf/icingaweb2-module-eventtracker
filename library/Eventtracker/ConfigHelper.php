<?php

namespace Icinga\Module\Eventtracker;

use gipfl\ZfDb\Adapter\Adapter as ZfDb;
use Icinga\Module\Monitoring\Object\MonitoredObject;

class ConfigHelper
{
    public static function getList($value)
    {
        if ($value !== null && \strlen($value) > 0) {
            $parts = \preg_split('/\s*,\s*/', $value, -1, \PREG_SPLIT_NO_EMPTY);

            return $parts;
        } else {
            return [];
        }
    }

    public static function fillPlaceHoldersForIssue($string, Issue $issue, ZfDb $db)
    {
        $ci = IcingaCi::eventuallyLoadForIssue($db, $issue);
        if ($ci) {
            if ($ci->object_type === 'service') {
                if ($host = IcingaCi::eventuallyLoad($db, $issue->get('host_name'))) {
                    $ci->host = $host;
                }
            }
        }
        return \preg_replace_callback('/({[^}]+})/', function ($match) use ($issue, $ci) {
            $property = \trim($match[1], '{}');
            list($property, $modifier) = static::extractPropertyModifier($property);
            if (\preg_match('/^(host|service)\.(.+)$/', $property, $match)) {
                if ($ci === null) {
                    return static::missingProperty($property);
                }
                $value = static::getIcingaCiProperty($ci, $property);
            } else {
                $value = static::getIssueProperty($issue, $property);
            }

            static::applyPropertyModifier($value, $modifier);

            return $value;
        }, $string);
    }


    /**
     * @param \stdClass $ci
     * @param $property
     * @return null
     */
    protected static function getIcingaCiProperty($ci, $property)
    {
        if (\preg_match('/^(host|service)\.(.+)$/', $property, $match)) {
            if ($ci->object_type === 'service') {
                if (! isset($ci->host)) {
                    return null;
                }

                $ci = $ci->host;
            }
            $property = $match[2];
        }

        return static::reallyGetCiProperty($ci, $property);
    }

    protected static function reallyGetCiProperty($ci, $property)
    {
        if (\preg_match('/^vars\.(.+)$/', $property, $match)) {
            $varName = $match[1];
            if (isset($ci->vars->$varName)) {
                return $ci->vars->$varName;
            }
        }
        if (isset($ci->$property)) {
            return $ci->$property;
        }

        return null;
    }

    protected static function missingProperty($property)
    {
        return '{' . $property . '}';
    }

    protected static function getIssueProperty(Issue $issue, $property)
    {
        if ($property === 'uuid') {
            return $issue->getNiceUuid();
        }

        if (\preg_match('/^attributes\.(.+)$/', $property, $match)) {
            return $issue->getAttribute($match[1]);
        }

        // TODO: check whether Issue has such property, and eventually use an interface
        return $issue->get($property);
    }

    /**
     * @param $string
     * @param Event|Issue|object $issue
     * @param callable|null $callback
     * @return string|null
     */
    public static function fillPlaceholders($string, $issue, callable $callback = null)
    {
        $replace = function ($match) use ($issue) {
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
                } else {
                    $value = $issue->$property;
                }
            } else {
                $value = $issue->$property;
            }
            if ($value === null) {
                return static::missingProperty($property);
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

    protected static function applyPropertyModifier(&$value, $modifier)
    {
        // Hint: $modifier could be null
        switch ($modifier) {
            case 'lower':
                $value = \strtolower($value);
                break;
        }
    }

    protected static function extractPropertyModifier($property)
    {
        $modifier = null;
        // TODO: make property modifiers dynamic
        if (\preg_match('/:lower$/', $property)) {
            $property = \preg_replace('/:lower$/', '', $property);
            $modifier = 'lower';
        }

        return [$property, $modifier];
    }
}
