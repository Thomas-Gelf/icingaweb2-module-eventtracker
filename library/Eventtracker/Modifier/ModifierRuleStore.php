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
    protected $sessionKey;
    public function __construct($ns, $uuid, $form)
    {
        $this->ns = $ns;
        $this->sessionKey = 'channelrules/' . $uuid->toString();
        $this->form = $form;
    }

    public function hasBeenModified(): bool
    {
        $sessionRules = $this->getSessionRules();
        if ($sessionRules === null) {
            return false;
        }
        if ($this->getStoredRules()->jsonSerialize() !== $sessionRules->jsonSerialize()) {
            return true;
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
    }

    public function getStoredRules(): ModifierChain
    {
        $rules = $this->form->getElementValue('rules');
        try {
            $modifier = JsonString::decode($rules);
        } catch (JsonDecodeException $e) {
            $modifier = [];
        }
        return ModifierChain::fromSerialization($modifier);
    }

    /**
     * @return mixed
     */
    public function getRules(): ModifierChain
    {
        return $this->getSessionRules() ?? $this->getStoredRules();
    }
}
