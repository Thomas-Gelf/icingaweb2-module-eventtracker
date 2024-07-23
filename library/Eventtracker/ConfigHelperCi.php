<?php

namespace Icinga\Module\Eventtracker;

use gipfl\ZfDb\Adapter\Adapter as ZfDb;

class ConfigHelperCi
{
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
        return \preg_replace_callback('/({[^}]+})/', function ($match) use ($issue, $ci, $db) {
            $property = \trim($match[1], '{}');
            list($property, $modifier) = ConfigHelper::extractPropertyModifier($property);
            if (\preg_match('/^(host|service)\.(.+)$/', $property, $match)) {
                if ($ci === null) {
                    return ConfigHelper::missingProperty($property);
                }
                $value = static::getIcingaCiProperty($ci, $property);
            } else {
                $value = ConfigHelper::getIssueProperty($issue, $property);
            }

            ConfigHelper::applyPropertyModifier($value, $modifier, $db);

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
}
