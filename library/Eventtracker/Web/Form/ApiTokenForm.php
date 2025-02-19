<?php

namespace Icinga\Module\Eventtracker\Web\Form;

use gipfl\Json\JsonString;
use Icinga\Module\Eventtracker\Web\Widget\Documentation;
use ipl\Html\Html;

class ApiTokenForm extends UuidObjectForm
{
    protected string $table = 'api_token';
    protected ?array $mainProperties = ['label', 'permissions'];
    protected array $multiSelectElements = ['permissions'];
    protected bool $storing = false;

    protected function assemble()
    {
        if ($this->uuid) {
            $docs = Documentation::link(
                $this->translate('Documentation'),
                'eventtracker',
                '61-REST_API',
                $this->translate('Documentation')
            );

            $this->add(Html::tag('dl', [
                Html::tag('dt', Html::tag('label', $this->translate('Token'))),
                Html::tag('dd', [
                    Html::tag('strong', $this->uuid->toString()),
                    Html::tag('p', ['class' => 'description'], [
                        Html::sprintf(
                            $this->translate(
                                'Please use this token as a Bearer Token in your Authentication-Header'
                                . ' when talking to our REST API: %s'
                            ),
                            Html::tag(
                                'pre',
                                "Accept: application/json\nAuthorization: Bearer ". $this->uuid->toString()
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
        $this->addElement('text', 'label', [
            'label'   => $this->translate('Label'),
        ]);
        $this->addElement('select', 'permissions', [
            'label'    => $this->translate('Permissions'),
            'required' => true,
            'multiple' => true,
            'options'  => [
                'issue/acknowledge' => $this->translate('Acknowledge Issues (not yet)'),
                'issue/close' => $this->translate('Close Issues'),
                'issues/fetch' => $this->translate('Fetch Issues'),
                'host_list/read' => $this->translate('Read permission for Host list'),
                'host_list/write' => $this->translate('Write permission for Host list'),
                'history/read' => $this->translate('Read permission for History'),
                'problemhandling/read' => $this->translate('Read permission for problem handling'),
                'problemhandling/write' => $this->translate('Write permission for problem handling'),
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
