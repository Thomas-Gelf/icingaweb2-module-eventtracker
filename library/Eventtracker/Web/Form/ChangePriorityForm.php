<?php

namespace Icinga\Module\Eventtracker\Web\Form;

use Icinga\Module\Eventtracker\Priority;
use ipl\Html\FormElement\SelectElement;
use ipl\Html\FormElement\SubmitElement;

class ChangePriorityForm extends InlineIssueForm
{
    protected function assemble()
    {
        $next = new SubmitElement('next', [
            'class' => 'link-button',
            'label' => $this->issue->get('priority'),
            'title' => $this->translate('Click to change priority')
        ]);
        $this->addElement($next);

        if ($this->hasBeenSent()) {
            $select = new SelectElement('new_priority', [
                'options' => Priority::ENUM,
                'value'   => $this->issue->get('priority'),
            ]);
            $submit = new SubmitElement('submit', [
                'label' => $this->translate('Set'),
            ]);
            $cancel = new SubmitElement('cancel', [
                'label' => $this->translate('Cancel')
            ]);

            $this->addElement($select);
            $this->addElement($submit);
            $this->addElement($cancel);
            if ($cancel->hasBeenPressed()) {
                $this->remove($select);
                $this->remove($submit);
                $this->remove($cancel);
            } else {
                $this->setSubmitButton($submit);
                $this->remove($next);
            }
        }
    }

    /**
     * @throws \Zend_Db_Adapter_Exception
     */
    public function onSuccess()
    {
        foreach ($this->issues as $issue) {
            $issue->set('priority', $this->getValue('new_priority'));
            $issue->storeToDb($this->db);
        }
    }
}
