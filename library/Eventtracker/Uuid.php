<?php

namespace Icinga\Module\Eventtracker;

class Uuid
{
    public static function generate()
    {
        return substr(sha1(microtime(true) . openssl_random_pseudo_bytes(20), true), 0, 16);
    }

    public static function toBinary($uuid)
    {
        // 401daca3-42cf-bd89-94a1-463e448ea8d1
        return hex2bin(str_replace('-', '', $uuid));
    }

    public static function toHex($bin)
    {
        $hex = bin2hex($bin);

        return implode('-', [
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        ]);
    }
}
