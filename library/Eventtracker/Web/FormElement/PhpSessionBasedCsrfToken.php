<?php

namespace Icinga\Module\Eventtracker\Web\FormElement;

use ipl\Html\FormElement\HiddenElement;

class PhpSessionBasedCsrfToken extends HiddenElement
{
    protected function assemble()
    {
        $this->setValue()
    }
}
