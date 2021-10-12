<?php

namespace Icinga\Module\Eventtracker\Web\Form;

use Icinga\Module\Eventtracker\Engine\Input;

class InputConfigForm extends UuidObjectForm
{
    protected $table = 'input';
    protected $mainProperties = ['label', 'implementation'];

    protected function assemble()
    {
        $this->addElement('text', 'label', [
            'label'   => $this->translate('Label'),
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

        $this->getImplementationSettingsForm($implementation)->enhanceConfigForm($this);
        $this->addButtons();
    }

    protected function getImplementationSettingsForm($implementation)
    {
        /** @var string|Input $class IDE hint */
        $class = $this->registry->getClassName($implementation);
        $formClass = $class::getSettingsSubForm();
        return new $formClass();
    }
}
