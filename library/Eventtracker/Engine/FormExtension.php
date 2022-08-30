<?php

namespace Icinga\Module\Eventtracker\Engine;

use ipl\Html\Form;

interface FormExtension
{
    public function enhanceForm(Form $form);
}
