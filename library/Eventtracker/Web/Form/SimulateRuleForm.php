<?php

namespace Icinga\Module\Eventtracker\Web\Form;

use gipfl\Web\Form;
use gipfl\Translation\TranslationHelper;
use Icinga\Module\Eventtracker\SampleObject;

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
            'options' => [null => $this->translate('- please choose -'), 'syslog' => $this->translate('Syslog')],
            'autosubmit' => true,
        ]);
        if ($simulationEntry = $this->getValue('simulation_entries')) {
            $sampleObject = SampleObject::getSampleObject($simulationEntry);
            $this->ns->set($this->sessionKey, $sampleObject);
        }
        $this->addElement('submit', 'submit', [
            'label' => $this->translate('Submit'),
        ]);
    }
}
