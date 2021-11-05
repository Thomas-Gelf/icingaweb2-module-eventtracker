<?php

namespace Icinga\Module\Eventtracker\Web\Form\Input;

use gipfl\Translation\TranslationHelper;
use Icinga\Module\Eventtracker\Engine\Input\FormExtension;
use ipl\Html\Form;
use Ramsey\Uuid\Uuid;

class RestApiForm implements FormExtension
{
    use TranslationHelper;

    public static function getLabel()
    {
        return static::getTranslator()->translate('REST API Token');
    }

    public function enhanceForm(Form $form)
    {
        $form->addElement('text', 'token', [
            'label'       => $this->translate('Token'),
            'required'    => true,
            'readonly'    => true,
            'description' => $this->translate(
                'REST API Token, required to authenticate and to identify as a specific Input'
            ),
            'value'       => Uuid::uuid4()->toString(),
        ]);
    }
}
