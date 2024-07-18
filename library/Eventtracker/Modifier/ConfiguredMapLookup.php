<?php

namespace Icinga\Module\Eventtracker\Modifier;

use gipfl\Json\JsonString;
use Icinga\Application\Config;
use ipl\Html\Html;
use ipl\Html\ValidHtml;
use RuntimeException;

/**
 * @deprecated: this is probably not in use anywhere, and should not be used
 */
class ConfiguredMapLookup extends MapLookup
{
    protected static $name = 'Lookup (and map) values via a configured Map';

    protected $map;

    protected function getMap()
    {
        if ($this->map === null) {
            $this->map = $this->loadMap();
        }

        return $this->map;
    }

    protected function loadMap()
    {
        $mapName = $this->settings->getRequired('map_name');
        if (! preg_match('/^[${A-Za-z0-9 _-}]+$/', $mapName)) {
            throw new RuntimeException("'$mapName' is not a valid map name");
        }
        $filename = dirname(Config::module('eventtracker')->getConfigFile()) . '/maps/' . $mapName . '.json';
        if (! is_file($filename)) {
            throw new RuntimeException('Cannot load map from ' . $filename);
        }

        return JsonString::decode(file_get_contents($filename));
    }

    public function describe(string $propertyName): ValidHtml
    {
        return Html::sprintf(
            'Lookup %s in the Map "%s"',
            Html::tag('strong', $propertyName),
            Html::tag('strong', $this->settings->getRequired('map_name'))
        );
    }
}
