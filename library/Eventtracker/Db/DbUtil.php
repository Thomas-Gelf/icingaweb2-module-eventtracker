<?php

namespace Icinga\Module\Eventtracker\Db;

use gipfl\ZfDb\Adapter\Adapter;
use gipfl\ZfDb\Adapter\Pdo\Pgsql;
use gipfl\ZfDb\Expr;

use function bin2hex;
use function is_array;
use function is_resource;
use function stream_get_contents;

class DbUtil
{
    public static function binaryResult($value)
    {
        if (is_resource($value)) {
            return stream_get_contents($value);
        }

        return $value;
    }

    /**
     * @param string|array $binary
     * @return Expr|Expr[]
     */
    public static function quoteBinary($binary, Adapter $db)
    {
        if (is_array($binary)) {
            return static::quoteArray($binary, 'quoteBinary', $db);
        }

        if ($binary === null) {
            return null;
        }

        if ($db instanceof Pgsql) {
            return new Expr("'\\x" . bin2hex($binary) . "'");
        }

        return new Expr('0x' . bin2hex($binary));
    }

    protected static function quoteArray($array, $method, $db): array
    {
        $result = [];
        foreach ($array as $bin) {
            $quoted = static::$method($bin, $db);
            $result[] = $quoted;
        }

        return $result;
    }
}
