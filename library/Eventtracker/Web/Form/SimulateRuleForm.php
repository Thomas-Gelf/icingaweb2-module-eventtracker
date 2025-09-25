<?php

namespace Icinga\Module\Eventtracker\Web\Form;

use gipfl\Web\Form;
use gipfl\Translation\TranslationHelper;
use Icinga\Module\Eventtracker\SampleObject;
use ipl\Html\FormElement\SubmitElement;

class SimulateRuleForm extends Form
{
    use TranslationHelper;
    protected $ns;
    protected $sessionKey;

    public function __construct($ns, $sessionKey)
    {
        $this->ns = $ns;
        $this->sessionKey = $sessionKey;
    }

    protected function assemble()
    {
        $this->addElement('select', 'simulation_entries', [
           'label' => $this->translate('Simulation entries'),
           'description' => $this->translate('Simulation entries'),
           'required' => true,
            'options' => [
                null => $this->translate('- please choose -'),
                'syslog' => $this->translate('Syslog'),
                'json' => $this->translate('JSON'),
            ],
            'autosubmit' => true,
        ]);
        if ($simulationEntry = $this->getValue('simulation_entries')) {
            $sampleObject = SampleObject::getSampleObject($simulationEntry);
            $this->ns->set($this->sessionKey, $sampleObject);
        }
        $this->addElement('submit', 'submit', [
            'label' => $this->translate('Submit'),
        ]);
        $this->addCancelButton();
    }
    protected function addCancelButton()
    {
        $button = $this->createElement('submit', 'delete', [
            'label' => $this->translate('Cancel'),
            'formnovalidate' => true,
        ]);
        assert($button instanceof SubmitElement);
        $this->cancelButton = $button;
        $submit = $this->getElement('submit');
        assert($submit instanceof SubmitElement);
        $decorator = $submit->getWrapper();
        assert($decorator instanceof Form\Decorator\DdDtDecorator);
        $dd = $decorator->dd();
        $dd->add($button);
        $this->registerElement($button);
    }

    public function hasBeenCancelled(): bool
    {
        return $this->cancelButton->hasBeenPressed();
    }
}
