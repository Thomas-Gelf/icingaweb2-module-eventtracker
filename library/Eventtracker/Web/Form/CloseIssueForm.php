<?php

namespace Icinga\Module\Eventtracker\Web\Form;

class CloseIssueForm extends InlineIssueForm
{
    protected function assemble()
    {
        $this->provideAction($this->translate('Close'), $this->translate('Manually close this issue'));
    }

    public function onSuccess()
    {
        foreach ($this->issues as $issue) {
            $issue->close($this->db);
        }
    }
}
