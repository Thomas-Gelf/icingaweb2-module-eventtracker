<?php

namespace Icinga\Module\Eventtracker\Web\Form;

class ReOpenIssueForm extends InlineIssueForm
{
    protected function assemble()
    {
        $this->provideAction($this->translate('Re-Open'), $this->translate('Manually re-open this issue'));
    }

    /**
     * @throws \Zend_Db_Adapter_Exception
     */
    public function onSuccess()
    {
        foreach ($this->issues as $issue) {
            $issue->set('status', 'open')->storeToDb($this->db);
        }
    }
}
