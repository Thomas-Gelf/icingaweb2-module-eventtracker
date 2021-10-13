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
     * @throws \gipfl\ZfDb\Adapter\Exception\AdapterException
     * @throws \gipfl\ZfDb\Statement\Exception\StatementException
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
