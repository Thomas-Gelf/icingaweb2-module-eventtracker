<?php

namespace Icinga\Module\Eventtracker\Daemon;

use Icinga\Application\Logger as IcingaLogger;
use Icinga\Application\Logger\LogWriter;
use Icinga\Exception\ConfigurationError;

class Logger extends IcingaLogger
{
    public static function replaceRunningInstance(LogWriter $writer, $level = null)
    {
        try {
            self::$instance->writer = $writer;
            if ($level !== null) {
                self::$instance->setLevel($level);
            }
        } catch (ConfigurationError $e) {
            self::$instance->error($e->getMessage());
        }
    }
}
