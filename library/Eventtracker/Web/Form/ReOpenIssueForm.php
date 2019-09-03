<?php

namespace Icinga\Module\Eventtracker\Web\Form;

use ipl\Html\FormElement\SubmitElement;

class ReOpenIssueForm extends InlineIssueForm
{
    protected function assemble()
    {
        $next = new SubmitElement('next', [
            'class' => 'link-button',
            'label' => $this->translate('[ Re-Open ]'),
            'title' => $this->translate('Manually re-open this issue')
        ]);
        $submit = new SubmitElement('submit', [
            'label' => $this->translate('Really re-open'),
        ]);
        $cancel = new SubmitElement('cancel', [
            'label' => $this->translate('Cancel')
        ]);
        $this->toggleNextSubmitCancel($next, $submit, $cancel);
    }

    /**
     * @throws \Zend_Db_Adapter_Exception
     */
    public function onSuccess()
    {
        $issue = $this->issue;
        $issue->set('status', 'open');
        $issue->storeToDb($this->db);
    }
}
