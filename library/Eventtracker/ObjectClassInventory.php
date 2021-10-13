<?php

namespace Icinga\Module\Eventtracker;

use gipfl\ZfDb\Adapter\Adapter as Db;
use gipfl\ZfDb\Adapter\Exception as DbException;

class ObjectClassInventory
{
    /** @var Db */
    protected $db;

    /** @var array */
    protected $classes;

    public function __construct(Db $db)
    {
        $this->db = $db;
        $this->refreshClasses();
    }

    /**
     * @param $className
     * @return string
     */
    public function requireClass($className)
    {
        if (! isset($this->classes[$className])) {
            $this->createNewObjectClass($className);
        }

        return $className;
    }

    /**
     * @param $className
     */
    protected function createNewObjectClass($className)
    {
        try {
            $this->db->insert('object_class', [
                'class_name' => $className,
            ]);
            $this->classes[$className] = $className;
        } catch (DbException $e) {
            $this->refreshClasses();
            if (! isset($this->classes[$className])) {
                throw $e;
            }
        }
    }

    protected function refreshClasses()
    {
        $this->classes = $this->db->fetchPairs(
            $this->db->select()
                ->from('object_class', [
                    'k' => 'class_name',
                    'v' => 'class_name',
                ])
        );
    }
}
