<?php

namespace Icinga\Module\Eventtracker\Modifier;

use gipfl\Translation\TranslationHelper;
use ipl\Html\Html;
use ipl\Html\ValidHtml;
use Ramsey\Uuid\Uuid;

class GenerateUuid extends BaseModifier
{
    use TranslationHelper;

    protected static ?string $name = 'Generate a UUID';

    public function transform($object, string $propertyName)
    {
        // TODO: other UUID versions?
        return Uuid::uuid4()->toString();
    }

    public function describe(string $propertyName): ValidHtml
    {
        return Html::sprintf(
            $this->translate('Generate a random UUIDv4 into %s'),
            Html::tag('strong', $propertyName),
        );
    }
}
