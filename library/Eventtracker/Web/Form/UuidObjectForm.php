<?php

namespace Icinga\Module\Eventtracker\Web\Form;

use gipfl\Translation\TranslationHelper;
use gipfl\Web\Form;
use Icinga\Module\Eventtracker\Db\ConfigStore;
use Icinga\Module\Eventtracker\Engine\Registry;
use Icinga\Web\Notification;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class UuidObjectForm extends Form
{
    use TranslationHelper;

    /** @var Registry */
    protected $registry;

    /** @var ConfigStore */
    protected $store;

    /** @var UuidInterface */
    protected $uuid;

    /** @var bool */
    protected $deleted = false;

    protected $table = 'NEEDS_TO_BE_OVERRIDDEN';

    protected $mainProperties;

    public function __construct(Registry $registry, ConfigStore $store)
    {
        $this->registry = $registry;
        $this->store = $store;
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
    }

    protected function addDeleteButton()
    {
        $button = $this->createElement('submit', 'delete', [
            'label' => $this->translate('Delete')
        ]);
        $this->addElement($button);
        $labelReally = $this->translate('YES, I really want to delete this');
        if ($button->hasBeenPressed()) {
            $this->remove($button);
            $this->addElement('submit', 'really_delete', [
                'label' => $labelReally,
            ]);
        }
        if ($this->getSentValue('really_delete') === $labelReally) {
            $this->store->deleteObject($this->table, $this->uuid);
            $this->deleted = true;
            Notification::success(sprintf($this->translate('%s has been deleted'), $this->getElementValue('label')));
        }
    }

    public function hasBeenDeleted()
    {
        return $this->deleted;
    }

    public function getUuid()
    {
        return $this->uuid;
    }

    public function onSuccess()
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
        $result = $this->store->storeObject($this->table, $properties);
        if ($result === true) {
            Notification::success(sprintf(
                $this->translate('%s has been modified'),
                $properties['label']
            ));
        } elseif ($result instanceof UuidInterface) {
            $this->uuid = $result;
            Notification::success($this->translate('A new XXX has been defined'));
        }
    }
}
