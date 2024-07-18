<?php

namespace Icinga\Module\Eventtracker;

use gipfl\ZfDb\Adapter\Adapter as ZfDb;
use Icinga\Module\Monitoring\Object\MonitoredObject;

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

    public static function missingProperty($property)
    {
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
                $value = $issue->$property ?? null;
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

    public static function applyPropertyModifier(&$value, $modifier)
    {
        // Hint: $modifier could be null
        switch ($modifier) {
            case 'lower':
                $value = \strtolower($value);
                break;
            case 'stripTags':
                $value = \strip_tags($value);
                break;
        }
    }

    public static function extractPropertyModifier($property)
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

        return [$property, $modifier];
    }
}
