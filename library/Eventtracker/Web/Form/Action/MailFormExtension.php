<?php

namespace Icinga\Module\Eventtracker\Web\Form\Action;

use gipfl\Translation\TranslationHelper;
use Icinga\Module\Eventtracker\Engine\FormExtension;
use ipl\Html\Form;
use ipl\Html\Html;

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
            'placeholder' => 'mail@eventtracker',
            'required'    => true,
        ]);
        $form->addElement('text', 'to', [
            'description' => $this->translate(
                'Address of the recipient to which the mails should be sent.'
            ),
            'label'       => $this->translate('To'),
            'required'    => true
        ]);
        $form->addElement('text', 'subject', [
            'description' => Html::sprintf(
                $this->translate(<<<'EOT'
You can use placeholders to have the subject replaced by real values.
Placeholders are expressed with %s where %s can reference any issue property.
Issue attributes can be accessed via  and custom variables via %s.
EOT
                ),
                Html::tag('b', '{placeholder}'),
                Html::tag('b', 'placeholder'),
                Html::tag('b', 'attributes.key'),
                Html::tag('b', 'host.vars.key')
            ),
            'label'       => $this->translate('Subject'),
            'placeholder' => '{severity} issue on {host_name} '
        ]);
        $form->addElement('textarea', 'body', [
            'description' => $this->translate(
                'You can use placeholders to have the body replaced by real values. See subject for details.'
            ),
            'label'       => $this->translate('Body'),
            'placeholder' => <<<'EOT'
{message}
Env: {attributes.env}
OS: {host.vars.os}'
EOT,
            'rows'        => 3
        ]);
    }
}
