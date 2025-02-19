<?php

namespace Icinga\Module\Eventtracker\Web\Form;

use gipfl\Web\InlineForm;
use function get_class;

class InstanceInlineForm extends InlineForm
{
    protected $instanceIdentifier;

    public function __construct($instanceIdentifier)
    {
        $this->instanceIdentifier = $instanceIdentifier;
    }

    protected function getUniqueFormName(): string
    {
        return get_class($this) . '|' . $this->instanceIdentifier;
    }
}
