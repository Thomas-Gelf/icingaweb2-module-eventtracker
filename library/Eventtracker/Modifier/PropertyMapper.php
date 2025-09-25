<?php

namespace Icinga\Module\Eventtracker\Modifier;

use stdClass;

use function array_key_exists;
use function property_exists;

class PropertyMapper
{
    protected array $map;

    /**
     * @param array $map
     */
    public function __construct(array $map)
    {
        $this->map = $map;
    }

    public function mapObject(stdClass $source): stdClass
    {
        $result = [];
        foreach ($this->map as $left => $right) {
            if (property_exists($source, $left)) {
                $result[$right] = $source->$left;
            }
        }

        return (object) $result;
    }

    public function mapArray(array $source): array
    {
        $result = [];
        foreach ($this->map as $left => $right) {
            if (array_key_exists($left, $source)) {
                $result[$right] = $source[$left];
            }
        }

        return $result;
    }
}
