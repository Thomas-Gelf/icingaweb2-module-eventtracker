<?php

namespace Icinga\Module\Eventtracker\Web\Form\Input;

use gipfl\Translation\TranslationHelper;
use Icinga\Module\Eventtracker\Engine\Input\InputFormExtension;
use ipl\Html\Form;

class KafkaInputForm implements InputFormExtension
{
    use TranslationHelper;

    public static function getLabel()
    {
        return static::getTranslator()->translate('Kafka');
    }

    public function enhanceConfigForm(Form $form)
    {
        $form->addElement('text', 'bootstrap_servers', [
            'label'       => $this->translate('Bootstrap Servers'),
            'value'       => 'localhost:9092',
            'required'    => true,
            'description' => $this->translate(
                'host1:port1,host2:port2,... - host/port pairs, used for initial Kafka cluster connection.'
                . ' The list doesn\'t have to be complete'
            ),
        ]);
        $form->addElement('text', 'group_id', [
            'label'       => $this->translate('Group ID'),
            'required'    => true,
            'description' => $this->translate('Kafka Consumer Group'),
        ]);
        $form->addElement('text', 'topic', [
            'label' => $this->translate('Topic'),
            'required'    => true,
            'description' => $this->translate('Kafka topic name'),
        ]);
        $form->addElement('select', 'transport_encryption', [
            'label' => $this->translate('Transport Encryption'),
            'options' => [
                null   => $this->translate('None / Plaintext'),
                'ssl'  => $this->translate('SSL'),
            ],
            'class' => 'autosubmit',
        ]);
        if ($form->getElementValue('transport_encryption') === 'ssl') {
            $form->addElement('text', 'ca_certificate', [
                'label' => $this->translate('CA certificate File'),
                'description' => $this->translate('/some/path/ca.pem, uses System trust store when not configured'),
            ]);
        }
        $form->addElement('select', 'authentication', [
            'label' => $this->translate('Authentication'),
            'options' => [
                null   => $this->translate('No authentication'),
                'sasl' => $this->translate('Username / Password (SASL)'),
                'ssl'  => $this->translate('SSL Client Certificate'),
            ],
            'class' => 'autosubmit',
        ]);
        switch ($form->getElementValue('authentication')) {
            case 'ssl':
                $form->addElement('text', 'client_certificate_file', [
                    'label'       => $this->translate('Client Certificate'),
                    'description' => $this->translate('Client Certificate file (/some/path/certificate.pem)'),
                ]);
                $form->addElement('text', 'client_key_file', [
                    'label'       => $this->translate('Client Private Key'),
                    'description' => $this->translate('Client Private Key file (/some/path/certificate.key)'),
                ]);
                break;
            case 'sasl':
                $form->addElement('text', 'username', [
                    'label' => $this->translate('Username'),
                ]);
                $form->addElement('password', 'password', [
                    'label' => $this->translate('Password'),
                ]);
                break;
        }
    }
}
