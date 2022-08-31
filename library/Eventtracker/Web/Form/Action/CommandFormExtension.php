<?php

namespace Icinga\Module\Eventtracker\Web\Form\Action;

use gipfl\Translation\TranslationHelper;
use Icinga\Module\Eventtracker\Engine\FormExtension;
use ipl\Html\Form;

class CommandFormExtension implements FormExtension
{
    use TranslationHelper;

    public function enhanceForm(Form $form)
    {
        $form->addElement('text', 'command', [
            'description' => $this->translate(
                'Command line to execute.'
            ),
            'label'       => $this->translate('Command line'),
            'placeholder' => '/bin/true',
            'required'    => true
        ]);
    }
}
