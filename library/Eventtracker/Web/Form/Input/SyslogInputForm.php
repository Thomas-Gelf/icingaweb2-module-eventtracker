<?php

namespace Icinga\Module\Eventtracker\Web\Form\Input;

use gipfl\Translation\TranslationHelper;
use Icinga\Module\Eventtracker\Engine\Input\InputFormExtension;
use ipl\Html\Form;

class SyslogInputForm implements InputFormExtension
{
    use TranslationHelper;

    public static function getLabel()
    {
        return static::getTranslator()->translate('Syslog');
    }

    public function enhanceConfigForm(Form $form)
    {
        $form->addElement('select', 'socket_type', [
            'label' => $this->translate('Socket Type'),
            'options' => [
                null   => $this->translate('- please choose -'),
                'unix' => $this->translate('UNIX (Stream) Socket'),
                'udp'  => $this->translate('UDP Socket'),
            ],
            'class' => 'autosubmit',
        ]);

        $socketType = $form->getElementValue('socket_type');
        switch ($socketType) {
            case 'unix':
                $form->addElement('text', 'socket_path', [
                    'label' => $this->translate('Socket Path'),
                    'value' => '/var/lib/icingaweb2/eventtracker-syslog.sock',
                ]);
                break;
            case 'udp':
                $form->addElement('text', 'listening_address', [
                    'label' => $this->translate('Listening address'),
                    'value' => '0.0.0.0',
                ]);
                $form->addElement('text', 'listening_port', [
                    'label' => $this->translate('Listening Port'),
                    'value' => '10514'
                ]);
                break;
        }
    }
}
