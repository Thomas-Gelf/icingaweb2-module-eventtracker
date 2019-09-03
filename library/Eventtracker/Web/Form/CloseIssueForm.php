<?php

namespace Icinga\Module\Eventtracker\Web\Form;

use ipl\Html\FormElement\SubmitElement;

class CloseIssueForm extends InlineIssueForm
{
    protected function assemble()
    {
        $next = new SubmitElement('next', [
            'class' => 'link-button',
            'label' => $this->translate('[ Close ]'),
            'title' => $this->translate('Manually close this issue')
        ]);
        $submit = new SubmitElement('submit', [
            'label' => $this->translate('Really Close'),
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
        $issue->set('status', 'closed');
        $issue->storeToDb($this->db);
    }
}
