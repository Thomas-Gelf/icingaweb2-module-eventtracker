<?php

namespace Icinga\Module\Eventtracker\Web\Form\Validator;

use gipfl\Web\Form\Validator\SimpleValidator;
use Icinga\Module\Eventtracker\Data\Json;
use Icinga\Module\Eventtracker\Modifier\ModifierChain;

class ModifierChainValidator extends SimpleValidator
{
    public function isValid($value)
    {
        try {
            ModifierChain::fromSerialization(Json::decode($value));
            return true;
        } catch (\Exception $e) {
            $this->addMessage($e->getMessage());
            return false;
        }
    }
}
