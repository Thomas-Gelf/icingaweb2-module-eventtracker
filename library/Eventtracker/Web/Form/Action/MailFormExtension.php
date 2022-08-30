<?php

namespace Icinga\Module\Eventtracker\Web\Form\Action;

use gipfl\Translation\TranslationHelper;
use Icinga\Module\Eventtracker\Engine\FormExtension;
use ipl\Html\Form;

class MailFormExtension implements FormExtension
{
    use TranslationHelper;

    public function enhanceForm(Form $form)
    {
        $form->addElement('text', 'from', [
            'description' => $this->translate(
                'Sender address with which the mails should be sent.'
            ),
            'label'       => $this->translate('From'),
            'required'    => true,
            'value'       => 'mail@eventtracker'
        ]);
        $form->addElement('text', 'to', [
            'description' => $this->translate(
                'Address of the recipient to which the mails should be sent.'
            ),
            'label'       => $this->translate('To'),
            'required'    => true
        ]);
        $form->addElement('text', 'subject', [
            'description' => $this->translate(
                'Template for the subject of the mails to be sent.'
            ),
            'label'       => $this->translate('Subject')
        ]);
        $form->addElement('textarea', 'body', [
            'description' => $this->translate(
                'Template for the body of the mails to be sent.'
            ),
            'label'       => $this->translate('Body')
        ]);
    }
}
