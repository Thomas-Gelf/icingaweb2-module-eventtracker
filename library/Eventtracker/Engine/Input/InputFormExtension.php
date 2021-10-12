<?php

namespace Icinga\Module\Eventtracker\Engine\Input;

use ipl\Html\Form;

interface InputFormExtension
{
    public function enhanceConfigForm(Form $form);
}
