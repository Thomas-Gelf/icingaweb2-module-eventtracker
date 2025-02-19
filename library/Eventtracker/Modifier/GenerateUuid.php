<?php

namespace Icinga\Module\Eventtracker\Modifier;

use Ramsey\Uuid\Uuid;

class GenerateUuid extends BaseModifier
{
    protected static ?string $name = 'Generate a UUID';

    public function transform($object, string $propertyName)
    {
        // TODO: other UUID versions?
        return Uuid::uuid4()->toString();
    }
}
