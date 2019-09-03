<?php

namespace Icinga\Module\Eventtracker\Web\Form;

use Icinga\Module\Eventtracker\Issue;
use Icinga\Module\Eventtracker\Web\Form;
use ipl\Html\FormElement\SubmitElement;
use Zend_Db_Adapter_Abstract as DbAdapter;

abstract class InlineIssueForm extends Form
{
    /** @var DbAdapter */
    protected $db;

    /** @var Issue */
    protected $issue;

    public function __construct(Issue $issue, DbAdapter $db)
    {
        $this->issue = $issue;
        $this->db = $db;
        $this->setMethod('POST');
        $this->addAttributes(['class' => 'inline']);
        $this->styleWithDirector();
    }

    protected function toggleNextSubmitCancel(
        SubmitElement $next,
        SubmitElement $submit,
        SubmitElement $cancel
    ) {
        if ($this->hasBeenSent()) {
            $this->addElement($submit);
            $this->addElement($cancel);
            if ($cancel->hasBeenPressed()) {
                $this->remove($submit);
                $this->remove($cancel);
            } else {
                $this->setSubmitButton($submit);
                $this->remove($next);
            }
        } else {
            $this->addElement($next);
        }
    }
}