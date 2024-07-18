<?php

namespace Icinga\Module\Eventtracker\Web\Form\Action;

use gipfl\Translation\TranslationHelper;
use gipfl\Web\Form\Element\Boolean;
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
Issue attributes can be accessed via %s and custom variables via %s.
Inline modifiers like in %s are allowed.
EOT
                ),
                Html::tag('b', '{placeholder}'),
                Html::tag('b', 'placeholder'),
                Html::tag('b', 'attributes.key'),
                Html::tag('b', 'host.vars.key'),
                Html::tag('b', '{host_name:lower}'),
            ),
            'label'       => $this->translate('Subject'),
            'placeholder' => '{severity} issue on {host_name} '
        ]);
        $form->addElement('textarea', 'body', [
            'description' => Html::sprintf($this->translate(
                'You can use placeholders to have the body replaced by real values. See subject for details.'
                . ' Inline modifiers like or %s are allowed'
            ), Html::tag('b', '{message:stripTags}')),
            'label'       => $this->translate('Body'),
            'placeholder' => <<<'EOT'
{message}
Env: {attributes.env}
OS: {host.vars.os}'
EOT,
            'rows'        => 3
        ]);
        $form->addElement('boolean', 'strip_tags', [
            'label'       => $this->translate('Strip HTML Tags'),
            'description' => Html::sprintf(
                $this->translate('Transforms %s into %s'),
                Html::tag('b', 'Some text, <a href="https://some/where">link</a>, ...'),
                Html::tag('b', 'Some text, link, ...'),
            ),
            'value'       => 'n',
        ]);
    }
}
