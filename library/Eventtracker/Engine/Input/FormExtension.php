<?php

namespace Icinga\Module\Eventtracker\Engine\Input;

use ipl\Html\Form;

interface FormExtension
{
    public function enhanceForm(Form $form);
}
