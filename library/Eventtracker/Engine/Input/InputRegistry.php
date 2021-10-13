<?php

namespace Icinga\Module\Eventtracker\Engine\Input;

use Icinga\Module\Eventtracker\Engine\Input;
use Icinga\Module\Eventtracker\Web\Form\FormUtils;

class InputRegistry
{
    protected $senderClasses = [
        'syslog'     => SyslogInput::class,
        'kafka'      => KafkaInput::class,
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
        return FormUtils::optionalEnum($implementations);


        return [
            // 'syslog'       => $this->translate('Syslog'),
            'scom_alert'   => $this->translate('SCOM Alerts'),
            'scom_monitor' => $this->translate('SCOM Monitors'),
            'ido'          => $this->translate('Icinga IDO Sync'),
            'icinga_api'   => $this->translate('Icinga 2 API Sync'),
        ];
    }
}
