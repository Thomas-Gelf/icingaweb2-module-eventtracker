<?php

namespace Icinga\Module\Eventtracker\Web\Form;

use gipfl\Translation\TranslationHelper;

class FormUtils
{
    public static function optionalEnum($list)
    {
        return [
            null => TranslationHelper::getTranslator()->translate('- please choose -')
        ] + $list;
    }
}
