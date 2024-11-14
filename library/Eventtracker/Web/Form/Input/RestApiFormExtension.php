<?php

namespace Icinga\Module\Eventtracker\Web\Form\Input;

use gipfl\Translation\TranslationHelper;
use Icinga\Module\Eventtracker\Engine\FormExtension;
use Icinga\Module\Eventtracker\Web\Widget\Documentation;
use ipl\Html\Form;
use ipl\Html\Html;
use Ramsey\Uuid\Uuid;

class RestApiFormExtension implements FormExtension
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
        if ($token = $form->getValue('token')) {
            $docs = Documentation::link(
                $this->translate('Documentation'),
                'eventtracker',
                '61-REST_API',
                $this->translate('Documentation')
            );

            $form->add(Html::tag('dl', [
                Html::tag('dt', Html::tag('label', $this->translate('Usage'))),
                Html::tag('dd', [
                    Html::tag('p', ['class' => 'description'], [
                        Html::sprintf(
                            $this->translate(
                                'Please use this token as a Bearer Token in your Authentication-Header'
                                . ' when talking to our REST API: %s'
                            ),
                            Html::tag(
                                'pre',
                                "Accept: application/json\nAuthorization: Bearer ". $token
                            )
                        ),
                        Html::sprintf(
                            $this->translate('Check our related %s for details'),
                            $docs
                        ),
                    ])
                ])
            ]));
        }
    }
}
