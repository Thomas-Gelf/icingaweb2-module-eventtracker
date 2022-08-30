<?php

namespace Icinga\Module\Eventtracker\Engine\Input;

use Icinga\Module\Eventtracker\Engine\SimpleRegistry;

class InputRegistry extends SimpleRegistry
{
    protected $implementations = [
        'syslog'     => SyslogInput::class,
        'kafka'      => KafkaInput::class,
        'restApi'    => RestApiInput::class,
//        'scom_alert' => ...
    ];
}
