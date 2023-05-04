<?php

namespace Icinga\Module\Eventtracker\Web\Form;

use gipfl\Translation\TranslationHelper;
use gipfl\Web\Form;
use Icinga\Module\Eventtracker\Db\ConfigStore;
use Icinga\Module\Eventtracker\Engine\Registry;
use Icinga\Web\Notification;
use ipl\Html\FormElement\SubmitElement;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class UuidObjectForm extends Form
{
    use TranslationHelper;

    /** @var ConfigStore */
    protected $store;

    /** @var ?Registry */
    protected $registry;

    /** @var ?UuidInterface */
    protected $uuid = null;

    /** @var bool */
    protected $deleted = false;

    protected $table = 'NEEDS_TO_BE_OVERRIDDEN';

    protected $mainProperties;

    public function __construct(ConfigStore $store, ?Registry $registry = null)
    {
        $this->store = $store;
        $this->registry = $registry;
    }

    public function populate($values)
    {
        if (isset($values['uuid'])) {
            $this->uuid = Uuid::fromBytes($values['uuid']);
            unset($values['uuid']);
        }
        return parent::populate($values);
    }

    protected function addButtons()
    {
        if ($this->uuid) {
            $this->addElement('submit', 'submit', [
                'label' => $this->translate('Store')
            ]);
            $this->addDeleteButton();
        } else {
            $this->addElement('submit', 'submit', [
                'label' => $this->translate('Create')
            ]);
        }
        $submit = $this->getElement('submit');
        assert($submit instanceof SubmitElement);
        $this->setSubmitButton($submit);
    }

    protected function addDeleteButton()
    {
        $button = $this->createElement('submit', 'delete', [
            'label' => $this->translate('Delete'),
            'formnovalidate' => true,
        ]);
        $submit = $this->getElement('submit');
        assert($submit instanceof SubmitElement);
        $decorator = $submit->getWrapper();
        assert($decorator instanceof Form\Decorator\DdDtDecorator);
        $dd = $decorator->dd();
        $dd->add($button);
        $this->registerElement($button);
        $label = $this->getObjectLabel();
        $labelReally = sprintf($this->translate('YES, I really want to delete %s'), $label);
        if ($button->hasBeenPressed()) {
            $dd->remove($button);
            $this->remove($button);
            $cancel = $this->createElement('submit', 'cancel', [
                'label' => $this->translate('Cancel'),
                'formnovalidate' => true,
            ]);
            $really = $this->createElement('submit', 'really_delete', [
                'label' => $labelReally,
                'formnovalidate' => true,
            ]);
            $this->registerElement($cancel);
            $this->registerElement($really);
            $dd->add([$cancel, $really]);
        }
        if ($this->getSentValue('really_delete') === $labelReally) {
            $this->store->deleteObject($this->table, $this->uuid);
            $this->deleted = true;
            Notification::success(sprintf($this->translate('%s has been deleted'), $this->getObjectLabel()));
        }
    }

    protected function getObjectLabel()
    {
        return $this->getElementValue('label', $this->translate('A new object'));
    }

    public function hasBeenDeleted(): bool
    {
        return $this->deleted;
    }

    public function getUuid(): ?UuidInterface
    {
        return $this->uuid;
    }

    protected function storeObject()
    {
        $values = $this->getValues();

        if ($this->mainProperties) {
            $properties = [];
            foreach ($this->mainProperties as $property) {
                $properties[$property] = $values[$property];
                unset($values[$property]);
            }

            if (! empty($values)) {
                $properties['settings'] = $values;
            }
        } else {
            $properties = $values;
        }

        if ($this->uuid) {
            $properties['uuid'] = $this->uuid->toString();
        }

        return $this->store->storeObject($this->table, $properties);
    }

    public function onSuccess()
    {
        $result = $this->storeObject();
        if ($result === true) {
            Notification::success(sprintf(
                $this->translate('%s has been modified'),
                $this->getObjectLabel()
            ));
        } elseif ($result instanceof UuidInterface) {
            $this->uuid = $result;
            Notification::success(sprintf($this->translate('%s has been created'), $this->getObjectLabel()));
        }
    }
}
