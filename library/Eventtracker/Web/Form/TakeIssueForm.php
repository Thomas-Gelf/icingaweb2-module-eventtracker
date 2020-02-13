<?php

namespace Icinga\Module\Eventtracker\Web\Form;

use Icinga\Authentication\Auth;

class TakeIssueForm extends InlineIssueForm
{
    protected function assemble()
    {
        $this->provideAction($this->translate('Take'), $this->translate('Take ownership for this issue'));
    }

    /**
     * @throws \Zend_Db_Adapter_Exception
     */
    public function onSuccess()
    {
        $username = Auth::getInstance()->getUser()->getUsername();
        foreach ($this->issues as $issue) {
            $issue->setOwner($username);
            $issue->storeToDb($this->db);
        }
    }
}
