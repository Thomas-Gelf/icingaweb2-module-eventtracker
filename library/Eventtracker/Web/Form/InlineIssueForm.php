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

    /** @var boolean|null */
    protected $hasBeenSubmitted;

    public function __construct(Issue $issue, DbAdapter $db)
    {
        $this->issue = $issue;
        $this->db = $db;
        $this->setMethod('POST');
        $this->addAttributes(['class' => 'inline']);
        $this->styleWithDirector();
    }

    protected function provideAction($label, $title = null)
    {
        $next = new SubmitElement('next', [
            'class' => 'link-button',
            'label' => sprintf('[ %s ]', $label),
            'title' => $title,
        ]);
        $submit = new SubmitElement('submit', [
            'label' => sprintf(
                $this->translate('Really %s'),
                $label
            )
        ]);
        $cancel = new SubmitElement('cancel', [
            'label' => $this->translate('Cancel')
        ]);
        $this->toggleNextSubmitCancel($next, $submit, $cancel);
    }

    public function setSubmitted($submitted = true)
    {
        $this->hasBeenSubmitted = (bool) $submitted;

        return $this;
    }

    public function hasBeenSubmitted()
    {
        if ($this->hasBeenSubmitted === null) {
            return parent::hasBeenSubmitted();
        } else {
            return $this->hasBeenSubmitted;
        }
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
                // HINT: we might also want to redirect on cancel and stop here,
                //       but currently we have no Response
                $this->setSubmitted(false);
                $this->remove($submit);
                $this->remove($cancel);
                $this->add($next);
                $this->setSubmitButton($next);
            } else {
                $this->setSubmitButton($submit);
                $this->remove($next);
            }
        } else {
            $this->addElement($next);
        }
    }
}
