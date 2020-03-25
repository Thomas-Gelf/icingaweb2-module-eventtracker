<?php

namespace Icinga\Module\Eventtracker;

use Icinga\Module\Monitoring\Object\Host;
use Icinga\Module\Monitoring\Object\MonitoredObject;

class ConfigHelper
{
    public static function getList($value)
    {
        if (\strlen($value)) {
            $parts = \preg_split('/\s*,\s*/', $value, -1, \PREG_SPLIT_NO_EMPTY);

            return $parts;
        } else {
            return [];
        }
    }

    /**
     * @param $string
     * @param Event|Issue|object $issue
     * @return string|null
     */
    public static function fillPlaceholders($string, $issue)
    {
        return \preg_replace_callback('/({[^}]+})/', function ($match) use ($issue) {
            $property = \trim($match[1], '{}');
            $modifier = null;
            // TODO: make property modifiers dynamic
            if (\preg_match('/:lower$/', $property)) {
                $property = \preg_replace('/:lower$/', '', $property);
                $modifier = 'lower';
            }
            if ($issue instanceof Issue && $property === 'uuid') {
                $value = $issue->getNiceUuid();
            } elseif ($issue instanceof Issue && \preg_match('/^attributes\.(.+)$/', $property, $pMatch)) {
                $value = $issue->getAttribute($pMatch[1]);
            } elseif ($issue instanceof Issue || $issue instanceof Event) {
                // TODO: check whether Issue has such property, and eventually use an interface
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

            switch ($modifier) {
                case 'lower':
                    $value = \strtolower($value);
                    break;
            }

            return $value;
        }, $string);
    }
}
