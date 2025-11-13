<?php

namespace Icinga\Module\Eventtracker\Reporting;

use gipfl\Translation\TranslationHelper;

// TODO: enum, once we require PHP 8
class AggregationSubject
{
    use TranslationHelper;

    public const HOST  = 'host_name';
    public const OBJECT   = 'object_name';
    public const OBJECT_CLASS   = 'object_class';
    public const PROBLEM_IDENTIFIER  = 'problem_identifier';

    public static function enum(): array
    {
        $t = self::getTranslator();
        return [
            self::HOST  => $t->translate('Host'),
            self::OBJECT  => $t->translate('Object'),
            self::OBJECT_CLASS  => $t->translate('Object Class'),
            self::PROBLEM_IDENTIFIER  => $t->translate('Problem Identifier'),
        ];
    }
}
