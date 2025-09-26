<?php

namespace Icinga\Module\Eventtracker\Modifier;

use gipfl\Json\JsonDecodeException;
use gipfl\Json\JsonString;

class ModifierRuleStore
{
    protected $ns;
    protected ModifierChain $rules;
    protected $uuid;
    protected $form;
    protected string $sessionKey;
    protected string $sessionKeyStoredChecksum;
    public function __construct($ns, $uuid, $form)
    {
        $this->ns = $ns;
        $this->sessionKey = 'channelrules/' . $uuid->toString();
        $this->sessionKeyStoredChecksum = $this->sessionKey . '/formerChecksum';
        $this->form = $form;
    }

    public function hasBeenModified(): bool
    {
        $sessionRules = $this->getSessionRules();
        if ($sessionRules === null) {
            return false;
        }

        if (!$this->getStoredRules()->equals($sessionRules)) {
            return true;
        }
        return false;
    }

    public function saveWouldDestroyOtherChanges(): bool
    {
        if ($checksum = $this->ns->get($this->sessionKeyStoredChecksum)) {
            return $checksum !== $this->getStoredRules()->getShortChecksum();
        }

        return false;
    }

    public function setModifierRules(ModifierChain $rules)
    {
        $this->rules = $rules;
        $this->ns->set($this->sessionKey, JsonString::encode($rules));
    }

    public function getSessionRules(): ?ModifierChain
    {
        $rules = $this->ns->get($this->sessionKey);
        if ($rules === null) {
            return null;
        }
        try {
            $modifier = JsonString::decode($rules);
        } catch (JsonDecodeException $e) {
            return null;
        }
        return ModifierChain::fromSerialization($modifier);
    }

    public function deleteSessionRules()
    {
        $this->ns->delete($this->sessionKey);
        $this->ns->delete($this->sessionKeyStoredChecksum);
    }

    public function getStoredRules(): ModifierChain
    {
        $rules = $this->form->getElementValue('rules');
        try {
            $modifier = JsonString::decode($rules);
        } catch (JsonDecodeException $e) {
            $modifier = [];
        }
        $chain = ModifierChain::fromSerialization($modifier);
        if (null === $this->ns->get($this->sessionKeyStoredChecksum)) {
            $this->ns->set($this->sessionKeyStoredChecksum, $chain->getShortChecksum());
        }

        return $chain;
    }

    public function storeRules(): void
    {
        $this->form->populate(['rules' => JsonString::encode($this->getRules())]);
        $this->form->storeObject();
        $this->deleteSessionRules();
    }

    public function getRules(): ModifierChain
    {
        return $this->getSessionRules() ?? $this->getStoredRules();
    }
}
