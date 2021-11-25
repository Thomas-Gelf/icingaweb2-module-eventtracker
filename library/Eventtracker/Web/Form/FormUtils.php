<?php

namespace Icinga\Module\Eventtracker\Web\Form;

use gipfl\Translation\StaticTranslator;

class FormUtils
{
    public static function optionalEnum($list)
    {
        return [
            null => StaticTranslator::get()->translate('- please choose -')
        ] + $list;
    }
}
