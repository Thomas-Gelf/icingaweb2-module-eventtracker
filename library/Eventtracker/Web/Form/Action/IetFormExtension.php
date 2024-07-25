<?php

namespace Icinga\Module\Eventtracker\Web\Form\Action;

use gipfl\Translation\TranslationHelper;
use Icinga\Module\Eventtracker\Engine\FormExtension;
use ipl\Html\Form;

class IetFormExtension implements FormExtension
{
    use TranslationHelper;

    public function enhanceForm(Form $form)
    {
        $form->addElement('select', 'iet_form', [
            'label' => $this->translate('iET Form'),
            'options' => [
                'CreateOperationalRequestForEventConsole' => 'CreateOperationalRequestForEventConsole',
                'CreateOperationalRequest' => 'CreateOperationalRequest',
                'MinimalMonitoringTicket' => 'MinimalMonitoringTicket',
            ]
        ]);
    }
}
