<?php

namespace Icinga\Module\Eventtracker\Modifier;

use InvalidArgumentException;

class ObjectUtils
{
    public static function deepClone($variable)
    {
        if ($variable === null || is_scalar($variable)) {
            return $variable;
        } elseif (is_array($variable)) {
            $result = [];
            foreach ($variable as $key => $value) {
                $result[$key] = static::deepClone($variable);
            }

            return $result;
        } elseif ($variable instanceof \stdClass) {
            return (object) static::deepClone((array) $variable);
        } else {
            throw new \InvalidArgumentException("Cannot clone variable, deep clone is not supported");
        }
    }

    /**
     * Extract variable names in the form ${var_name} from a given string
     *
     * @param  string $string
     *
     * @return array  List of variable names (without ${})
     */
    public static function extractVariableNames($string)
    {
        if (preg_match_all('/\${([^}]+)}/', $string, $m, PREG_PATTERN_ORDER)) {
            return $m[1];
        } else {
            return array();
        }
    }

    /**
     * Whether the given string contains variable names in the form ${var_name}
     *
     * @param  string $string
     *
     * @return bool
     */
    public static function hasVariables($string)
    {
        return preg_match('/\${([^}]+)}/', $string);
    }

    /**
     * Recursively extract a value from a nested structure
     *
     * For a $val looking like
     *
     * { 'vars' => { 'disk' => { 'sda' => { 'size' => '256G' } } } }
     *
     * and a key vars.disk.sda given as [ 'vars', 'disk', 'sda' ] this would
     * return { size => '255GB' }
     *
     * @param  string $val  The value to extract data from
     * @param  array  $keys A list of nested keys pointing to desired data
     *
     * @return mixed
     */
    protected static function getDeepValue($val, array $keys)
    {
        $key = array_shift($keys);
        if (! property_exists($val, $key)) {
            return null;
        }

        if (empty($keys)) {
            return $val->$key;
        }

        return static::getDeepValue($val->$key, $keys);
    }

    /**
     * Return a specific value from a given row object
     *
     * Supports also keys pointing to nested structures like vars.disk.sda
     *
     * @param  object $row  stdClass object providing property values
     * @param  string $var  Variable/property name
     *
     * @return mixed
     */
    public static function getSpecificValue($row, $var)
    {
        if (strpos($var, '.') === false) {
            if (! property_exists($row, $var)) {
                return null;
            }

            return $row->$var;
        } else {
            $parts = explode('.', $var);
            $main = array_shift($parts);
            if (! property_exists($row, $main)) {
                return null;
            }

            if (! is_object($row->$main)) {
                throw new InvalidArgumentException(sprintf(
                    'Data is not nested, cannot access %s: %s',
                    $var,
                    var_export($row, 1)
                ));
            }

            return static::getDeepValue($row->$main, $parts);
        }
    }

    public static function setSpecificValue($object, $var, $value)
    {
        if (strpos($var, '.') === false) {
            $object->$var = $value;
            return;
        }

        static::setDeepValue($object, $value, explode('.', $var));
    }

    public static function unsetSpecificValue($object, $var)
    {
        if (strpos($var, '.') === false) {
            unset($object->$var);
            return;
        }

        static::unsetDeepValue($object, explode('.', $var));
    }

    protected static function setDeepValue($object, $val, array $keys)
    {
        $key = array_shift($keys);
        if (empty($keys)) {
            $object->$key = $val;
            return;
        }
        if (! property_exists($object, $key) || ! is_object($object->$key)) {
            $object->$key = (object) [];
        }
        // TODO: setDeepValue was in the parenthesis above. Check in the Director!
        static::setDeepValue($object->$key, $val, $keys);
    }

    protected static function unsetDeepValue($object, array $keys)
    {
        $key = array_shift($keys);
        if (empty($keys)) {
            unset($object->$key);
            return;
        }
        if (! property_exists($object, $key)) {
            return;
        }
        if (! is_object($object->$key)) {
            throw new InvalidArgumentException(sprintf(
                'Data is not nested, cannot unset %s: %s',
                $key,
                var_export($object, 1)
            ));
        }

        static::unsetDeepValue($object->$key, $keys);
    }

    /**
     * Fill variables in the given string pattern
     *
     * This replaces all occurrences of ${var_name} with the corresponding
     * property $row->var_name of the given row object. Missing variables are
     * replaced by an empty string. This works also fine in case there are
     * multiple variables to be found in your string.
     *
     * @param  string $string String with optional variables/placeholders
     * @param  object $row    stdClass object providing property values
     *
     * @return string
     */
    public static function fillVariables($string, $row)
    {
        if (preg_match('/^\${([^}]+)}$/', $string, $m)) {
            return static::getSpecificValue($row, $m[1]);
        }

        $func = function ($match) use ($row) {
            return static::getSpecificValue($row, $match[1]);
        };

        return preg_replace_callback('/\${([^}]+)}/', $func, $string);
    }

    public static function getRootVariables($vars)
    {
        $res = array();
        foreach ($vars as $p) {
            if (false === ($pos = strpos($p, '.')) || $pos === strlen($p) - 1) {
                $res[] = $p;
            } else {
                $res[] = substr($p, 0, $pos);
            }
        }

        if (empty($res)) {
            return array();
        }

        return array_combine($res, $res);
    }
}
