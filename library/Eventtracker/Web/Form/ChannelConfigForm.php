<?php

namespace Icinga\Module\Eventtracker\Web\Form;

use Icinga\Module\Eventtracker\Web\Form\Validator\ModifierChainValidator;

class ChannelConfigForm extends UuidObjectForm
{
    protected string $table = 'channel';
    protected array $multiSelectElements = ['input_uuids'];

    protected function assemble()
    {
        $this->addElement('text', 'label', [
            'label' => $this->translate('Label'),
            'required' => true,
        ]);
        /*
        $this->addElement('textarea', 'rules', [
            'label' => $this->translate('Rules'),
            'description' => $this->translate('For now, these are JSON-encoded rules'),
            'validators' => [
                new ModifierChainValidator()
            ],
            'value' => '[]',
            'required' => true,
        ]);
        */
        $this->addHidden('rules', '[]');
        $this->addElement('select', 'input_uuids', [
            'label'       => $this->translate('Single Inputs'),
            'description' => $this->translate('Wire specific inputs to this channel'),
            'options'     => $this->store->enumObjects('input'),
            'multiple'    => true,
        ]);
        $this->addElement('select', 'input_implementation', [
            'label' => $this->translate('Input Implementations'),
            'description' => $this->translate('Wire all inputs of a specific type to this channel'),
            'options' => FormUtils::optionalEnum($this->registry->listImplementations()),
        ]);
        $this->addElement('select', 'bucket_uuid', [
            'label' => $this->translate('Bucket'),
            'description' => $this->translate('Use this bucket for rate limiting purposes'),
            'options' => FormUtils::optionalEnum($this->store->enumObjects('bucket')),
            'class'   => 'autosubmit',
        ]);
        if ($this->getValue('bucket_uuid')) {
            $this->addHidden('bucket_name', '');
        } else {
            $this->addElement('text', 'bucket_name', [
                'label' => $this->translate('Bucket name'),
                'description' => $this->translate(
                    'Alternatively, you might want to pick a bucket name from event properties,'
                    . ' e.g. {bucket}. Nested properties (like {attributes.bucket}) are not yet supported'
                ),
            ]);
        }
        $this->addButtons();
    }
}
