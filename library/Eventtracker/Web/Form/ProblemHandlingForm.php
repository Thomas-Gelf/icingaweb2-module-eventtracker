<?php

namespace Icinga\Module\Eventtracker\Web\Form;

class ProblemHandlingForm extends UuidObjectForm
{
    protected $table = 'problem_handling';

    protected $mainProperties = ['label', 'instruction_url', 'trigger_actions'];

    protected function assemble()
    {
        $this->addElement('text', 'label', [
            'label'    => $this->translate('Label'),
            'required' => true
        ]);
        $this->addElement('text', 'instruction_url', [
            'label'       => $this->translate('Instructions (URL)'),
            'description' => $this->translate(
                'URL pointing to a knowledge-base or similar, with instructions on how to deal with this problem'
            ),
            'required' => true
        ]);
        $this->addElement('select', 'trigger_actions', [
            'label'       => $this->translate('Trigger Actions'),
            'description' => $this->translate(
                'Whether configured actions should be triggered for this problem'
            ),
            'options'     => [
                'y' => $this->translate('yes'),
                'n' => $this->translate('no')
            ],
            'required'    => true,
            'value'       => 'y'
        ]);

        $this->addButtons();
    }
}
