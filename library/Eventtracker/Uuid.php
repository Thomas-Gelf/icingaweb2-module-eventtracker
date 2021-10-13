<?php

namespace Icinga\Module\Eventtracker;

use Ramsey\Uuid\Uuid as RamseyUuid;

class Uuid
{
    /**
     * @deprecated
     * @return string
     * @throws \Exception
     */
    public static function generate()
    {
        return RamseyUuid::uuid4()->getBytes();
    }

    /**
     * @deprecated
     * @param string $uuid
     * @return string
     */
    public static function toBinary($uuid)
    {
        return RamseyUuid::fromString($uuid)->getBytes();
    }

    /**
     * @deprecated
     * @param string $bin
     * @return string
     */
    public static function toHex($bin)
    {
        return RamseyUuid::fromBytes($bin)->toString();
    }
}
