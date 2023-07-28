<?php

namespace Icinga\Module\Eventtracker\Web\Form;

use gipfl\Json\JsonString;
use ipl\Html\Html;

class ApiTokenForm extends UuidObjectForm
{
    protected $table = 'api_token';
    protected $mainProperties = ['label', 'permissions'];
    protected $storing = false;

    protected function assemble()
    {
        if ($this->uuid) {
            $this->add(Html::tag('dl', [
                Html::tag('dt', Html::tag('label', $this->translate('Token'))),
                Html::tag('dd', [
                    Html::tag('strong', $this->uuid->toString()),
                    Html::tag('p', ['class' => 'description'], Html::sprintf(
                        $this->translate(
                            'Please use this token as a Bearer Token in your Authentication-Header'
                            . ' when talking to our REST API: %s'
                        ),
                        Html::tag('pre', 'Authorization: Bearer '. $this->uuid->toString())
                    ))
                ])
            ]));
        }
        $this->addElement('text', 'label', [
            'label'   => $this->translate('Label'),
        ]);
        $this->addElement('select', 'permissions', [
            'label'    => $this->translate('Permissions'),
            'required' => true,
            'multiple' => true,
            'options'  => [
                'issue/acknowledge' => $this->translate('Acknowledge Issues'),
                'issue/close' => $this->translate('Close Issues'),
            ],
        ]);
        $this->addButtons();
    }

    public function getValues()
    {
        $values = parent::getValues();
        if ($this->storing) {
            $values['permissions'] = JsonString::encode($values['permissions']);
        }

        return $values;
    }

    public function onSuccess()
    {
        $this->storing = true;
        parent::onSuccess();
        $this->storing = false;
    }
}
