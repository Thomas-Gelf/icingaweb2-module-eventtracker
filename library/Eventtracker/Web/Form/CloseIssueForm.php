<?php

namespace Icinga\Module\Eventtracker\Web\Form;

class CloseIssueForm extends InlineIssueForm
{
    protected function assemble()
    {
        $this->provideAction($this->translate('Close'), $this->translate('Manually close this issue'));
    }

    /**
     * @throws \Zend_Db_Adapter_Exception
     */
    public function onSuccess()
    {
        $this->issue->close($this->db);
    }
}
