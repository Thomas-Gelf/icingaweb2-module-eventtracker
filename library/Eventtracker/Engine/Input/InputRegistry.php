<?php

namespace Icinga\Module\Eventtracker\Engine\Input;

use Icinga\Module\Eventtracker\Engine\Input;
use Icinga\Module\Eventtracker\Engine\Registry;

class InputRegistry implements Registry
{
    protected $implementations = [
        'syslog'     => SyslogInput::class,
        'kafka'      => KafkaInput::class,
        'restApi'    => RestApiInput::class,
        // 'scom_alert' =>
    ];

    public function getInstance(string $identifier): Input
    {
        $class = $this->getClassName($identifier);
        return new $class;
    }

    public function addImplementation(string $identifier, string $class)
    {
        $this->implementations[$identifier] = $class;
    }

    public function getClassName(string $identifier): string
    {
        // TODO: Throw if not set
        return $this->implementations[$identifier];
    }

    public function listImplementations(): array
    {
        /** @var string|Input $class */
        $implementations = [];
        foreach ($this->implementations as $key => $class) {
            $implementations[$key] = $class::getLabel();
        }

        return $implementations;
    }
}
