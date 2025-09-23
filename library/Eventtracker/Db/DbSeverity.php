<?php

namespace Icinga\Module\Eventtracker\Db;

use Icinga\Module\Eventtracker\Severity;
use RuntimeException;

class DbSeverity
{
    protected const MAP = [
        Severity::DEBUG         => 1,
        Severity::INFORMATIONAL => 2,
        Severity::NOTICE        => 3,
        Severity::WARNING       => 4,
        Severity::ERROR         => 5,
        Severity::CRITICAL      => 6,
        Severity::ALERT         => 7,
        Severity::EMERGENCY     => 8,
    ];

    public static function getNumeric(string $severity): int
    {
        if (!isset(self::MAP[$severity])) {
            throw new RuntimeException("Asking for invalid severity: '$severity'");
        }

        return self::MAP[$severity];
    }
}
