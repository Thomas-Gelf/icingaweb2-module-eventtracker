<?php

namespace Icinga\Module\Eventtracker\Web\Form;

use Icinga\Authentication\Auth;
use Icinga\Module\Eventtracker\Issue;

class CloseIssueForm extends InlineIssueForm
{
    protected function assemble()
    {
        $this->provideAction($this->translate('Close'), $this->translate('Manually close this issue'));
    }

    public function onSuccess()
    {
        foreach ($this->issues as $issue) {
            Issue::closeIssue($issue, $this->db, 'Manually closed', Auth::getInstance()->getUser()->getUsername());
        }
    }
}
