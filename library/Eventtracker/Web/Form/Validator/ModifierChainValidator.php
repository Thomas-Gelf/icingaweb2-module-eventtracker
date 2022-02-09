<?php

namespace Icinga\Module\Eventtracker\Web\Form\Validator;

use gipfl\Json\JsonString;
use gipfl\Web\Form\Validator\SimpleValidator;
use Icinga\Module\Eventtracker\Modifier\ModifierChain;

class ModifierChainValidator extends SimpleValidator
{
    public function isValid($value)
    {
        try {
            ModifierChain::fromSerialization(JsonString::decode($value));
            return true;
        } catch (\Exception $e) {
            $this->addMessage($e->getMessage());
            return false;
        }
    }
}
