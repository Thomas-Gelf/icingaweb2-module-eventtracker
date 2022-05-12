<?php

namespace Icinga\Module\Eventtracker\Web\Form;

use Icinga\Module\Eventtracker\Web\Form\Validator\ModifierChainValidator;

class ChannelConfigForm extends UuidObjectForm
{
    protected $table = 'channel';

    protected function assemble()
    {
        $this->addElement('text', 'label', [
            'label' => $this->translate('Label'),
            'required' => true,
        ]);
        $this->addElement('textarea', 'rules', [
            'label' => $this->translate('Rules'),
            'description' => $this->translate('For now, these are JSON-encoded rules'),
            'validators' => [
                new ModifierChainValidator()
            ],
            'value' => '[]',
            'required' => true,
        ]);
        $this->addElement('multiSelect', 'input_uuids', [
            'label'       => $this->translate('Single Inputs'),
            'description' => $this->translate('Wire specific inputs to this channel'),
            'options'     => $this->store->enumObjects('input'),
        ]);
        $this->addElement('select', 'input_implementation', [
            'label' => $this->translate('Input Implementations'),
            'description' => $this->translate('Wire all inputs of a specific type to this channel'),
            'options' => FormUtils::optionalEnum($this->registry->listImplementations()),
        ]);
        $this->addButtons();
    }
}
