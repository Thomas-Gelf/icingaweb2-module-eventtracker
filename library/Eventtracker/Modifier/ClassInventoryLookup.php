<?php

namespace Icinga\Module\Eventtracker\Modifier;

use Icinga\Module\Eventtracker\DbFactory;
use Icinga\Module\Eventtracker\ObjectClassInventory;

class ClassInventoryLookup extends BaseModifier
{
    protected static ?string $name = 'Class Inventory Lookup';

    protected $XXinstanceDescriptionPattern = 'Transforms...';

    protected ?ObjectClassInventory $classes = null;

    protected function classes(): ObjectClassInventory
    {
        if ($this->classes === null) {
            // TODO: inject DB. requiredResources?
            $this->classes = new ObjectClassInventory(DbFactory::db());
        }

        return $this->classes;
    }

    protected function simpleTransform($value)
    {
        return $this->classes()->requireClass($value);
    }
}
