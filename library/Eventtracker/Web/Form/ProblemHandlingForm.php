<?php

namespace Icinga\Module\Eventtracker\Web\Form;

class ProblemHandlingForm extends UuidObjectForm
{
    protected string $table = 'problem_handling';
    protected ?array $mainProperties = ['label', 'instruction_url', 'trigger_actions', 'enabled'];

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
        /*
        // Disabled for now
        $this->addElement('boolean', 'trigger_actions', [
            'label'       => $this->translate('Trigger Actions'),
            'description' => $this->translate(
                'Whether configured actions should be triggered for this problem'
            ),
            'required'    => true,
            'value'       => 'y'
        ]);
        */
        $this->addHidden('trigger_actions', 'y');
        /*
        $this->addElement('boolean', 'enabled', [
            'label'       => $this->translate('Enabled'),
            'description' => $this->translate(
                'Whether configured actions should be triggered for this problem'
            ),
            'required'    => true,
            'value'       => 'y'
        ]);
        */
        $this->addHidden('enabled', 'y');
        $this->addButtons();
    }
}
