<?php

namespace Icinga\Module\Eventtracker\Web\Form;

use gipfl\IcingaWeb2\Icon;
use gipfl\Translation\TranslationHelper;
use Icinga\Module\Eventtracker\Incident;
use Icinga\Module\Eventtracker\Web\Form;
use ipl\Html\FormElement\SelectElement;
use ipl\Html\FormElement\SubmitElement;
use ipl\Html\Html;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend_Db_Adapter_Abstract as DbAdapter;

class GiveOwnerShipForm extends Form
{
    use TranslationHelper;

    /** @var DbAdapter */
    protected $db;

    /** @var Incident */
    protected $incident;

    public function __construct(Incident $incident, DbAdapter $db)
    {
        $this->incident = $incident;
        $this->db = $db;
        $this->setMethod('POST');
        $this->addAttributes(['class' => 'inline']);
        $this->styleWithDirector();
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response = null)
    {
        $this->handleRequest($request);
    }

    protected function assemble()
    {
        $this->add(Html::tag('strong', $this->translate('Give to:')));
        $next = new SubmitElement('next', [
            'class' => 'link-button',
            'label' => $this->translate('[..]'),
            'title' => $this->translate('Give this issue to a specific user')
        ]);
        $this->addElement($next);

        if ($this->hasBeenSent()) {
            $select = new SelectElement('new_owner', [
                'options' => [
                    'tom' => 'Thomas Gelf (tom)',
                    'zsa' => 'Sàrosi Zoltàn (zsa)',
                    null => $this->translate('Nobody in particular'),
                ],
                'value' => $this->incident->get('owner'),
            ]);
            $submit = new SubmitElement('submit', [
                'label' => $this->translate('Set'),
            ]);
            $cancel = new SubmitElement('cancel', [
                'label' => $this->translate('Cancel')
            ]);

            $this->addElement($select);
            $this->addElement($submit);
            $this->addElement($cancel);
            if ($cancel->hasBeenPressed()) {
                $this->remove($select);
                    $this->remove($submit);
                $this->remove($cancel);
            } else {
                $this->setSubmitButton($submit);
                $this->remove($next);
            }
        }
    }

    /**
     * @throws \Zend_Db_Adapter_Exception
     */
    public function onSuccess()
    {
        $incident = $this->incident;
        $incident->setOwner($this->getValue('new_owner'));
        $incident->storeToDb($this->db);
    }
}
