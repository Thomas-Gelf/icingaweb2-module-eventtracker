<?php

namespace Icinga\Module\Eventtracker\Modifier;

use Icinga\Module\Eventtracker\DbFactory;
use Icinga\Module\Eventtracker\ObjectClassInventory;

class ClassInventoryLookup extends BaseModifier
{
    protected static $name = 'Class Inventory Lookup';

    protected $XXinstanceDescriptionPattern = 'Transforms...';

    /** @var ObjectClassInventory */
    protected $classes;

    /**
     * @return ObjectClassInventory
     */
    protected function classes()
    {
        if ($this->classes === null) {
            // TODO: inject DB. requiredResources?
            $this->classes = new ObjectClassInventory(DbFactory::db());
        }

        return $this->classes;
    }

    /**
     * @throws \Zend_Db_Adapter_Exception
     */
    protected function simpleTransform($value)
    {
        return $this->classes()->requireClass($value);
    }
}
