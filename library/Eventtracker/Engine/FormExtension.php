<?php

namespace Icinga\Module\Eventtracker\Engine;

use gipfl\Web\Form;

interface FormExtension
{
    public function enhanceForm(Form $form);
}
