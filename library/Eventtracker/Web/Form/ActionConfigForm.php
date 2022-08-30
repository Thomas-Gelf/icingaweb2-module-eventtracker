<?php

namespace Icinga\Module\Eventtracker\Web\Form;

use Icinga\Module\Eventtracker\Engine\Action;
use Icinga\Module\Eventtracker\Engine\FormExtension;

class ActionConfigForm extends UuidObjectForm
{
    protected $table = 'action';

    protected $mainProperties = ['label', 'implementation', 'enabled', 'filter'];

    protected function assemble()
    {
        $this->addElement('text', 'label', [
            'label' => $this->translate('Label'),
        ]);
        $this->addElement('select', 'enabled', [
            'label'    => $this->translate('Enabled'),
            'options'  => [
                'y' => $this->translate('yes'),
                'n' => $this->translate('no')
            ],
            'required' => true,
            'value'    => 'y'
        ]);
        $this->addElement('text', 'filter', [
            'label' => $this->translate('Filter'),
        ]);
        $this->addElement('select', 'implementation', [
            'label'    => $this->translate('Implementation'),
            'required' => true,
            'options'  => FormUtils::optionalEnum($this->registry->listImplementations()),
            'class'    => 'autosubmit',
        ]);
        $implementation = $this->getElementValue('implementation');
        if ($implementation === null) {
            return;
        }

        $this->getImplementationSettingsForm($implementation)->enhanceForm($this);
        $this->addButtons();
    }

    protected function getImplementationSettingsForm($implementation): FormExtension
    {
        /** @var string|Action $class IDE hint */
        $class = $this->registry->getClassName($implementation);

        return $class::getFormExtension();
    }
}
