<?php

namespace Icinga\Module\Eventtracker\Web\Form;

use ipl\Html\FormElement\SelectElement;
use ipl\Html\FormElement\SubmitElement;
use ipl\Html\Html;

class GiveOwnerShipForm extends InlineIssueForm
{
    protected function assemble()
    {
        $next = new SubmitElement('next', [
            'class' => 'link-button',
            'label' => $this->translate('[ Give ]'),
            'title' => $this->translate('Give this issue to a specific user')
        ]);
        $this->addElement($next);
        if (count($this->issues) === 1) {
            $current = \current($this->issues)->get('owner');
        } else {
            $current = null;
        }

        if ($this->hasBeenSent()) {
            $label = Html::tag('strong', $this->translate('Give to:'));
            $this->add($label);
            $possibleOwners = [];
            $select = new SelectElement('new_owner', [
                'options' => [
                    null => $this->translate('Nobody in particular'),
                ] + $possibleOwners,
                'value' => $current,
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
                $this->remove($label);
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
     * @throws \gipfl\ZfDb\Adapter\Exception\AdapterException
     * @throws \gipfl\ZfDb\Statement\Exception\StatementException
     */
    public function onSuccess()
    {
        foreach ($this->issues as $issue) {
            $issue->setOwner($this->getValue('new_owner'));
            $issue->storeToDb($this->db);
        }
    }
}
