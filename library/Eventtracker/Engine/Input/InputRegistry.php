<?php

namespace Icinga\Module\Eventtracker\Engine\Input;

use Icinga\Module\Eventtracker\Engine\Input;

class InputRegistry
{
    protected $senderClasses = [
        'syslog'     => SyslogInput::class,
        'kafka'      => KafkaInput::class,
        'restApi'    => RestApiInput::class,
        // 'scom_alert' =>
    ];

    /**
     * @param string $identifier
     * @return Input
     */
    public function getInstance($identifier)
    {
        $class = $this->getClassName($identifier);
        return new $class;
    }

    public function addSender($identifier, $class)
    {
        $this->senderClasses[$identifier] = $class;
    }

    public function getClassName($identifier)
    {
        // TODO: Throw if not set
        return $this->senderClasses[$identifier];
    }

    public function listImplementations()
    {
        /** @var string|Input $class */
        $implementations = [];
        foreach ($this->senderClasses as $key => $class) {
            $implementations[$key] = $class::getLabel();
        }

        return $implementations;
    }
}
