<?php

namespace Icinga\Module\Eventtracker\Modifier;

use gipfl\Json\JsonString;
use gipfl\Translation\TranslationHelper;
use Icinga\Application\Config;
use Icinga\Module\Eventtracker\Web\Form\ChannelRuleForm;
use ipl\Html\Html;
use ipl\Html\ValidHtml;
use RuntimeException;

class DiskMapLookup extends MapLookup
{
    use TranslationHelper;

    protected static ?string $name = 'Lookup (and map) values on disk';

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
        if (! preg_match('/^[A-Za-z0-9 _-]+$/', $mapName)) {
            throw new RuntimeException("'$mapName' is not a valid map name");
        }
        $filename = self::getMapConfigDirectory() . "/$mapName.json";
        if (! is_file($filename)) {
            throw new RuntimeException('Cannot load map from ' . $filename);
        }

        return JsonString::decode(file_get_contents($filename));
    }

    public function describe(string $propertyName): ValidHtml
    {
        return Html::sprintf(
            $this->translate('Lookup %s in the Map "%s"'),
            Html::tag('strong', $propertyName),
            Html::tag('strong', $this->settings->getRequired('map_name'))
        );
    }

    public static function extendSettingsForm(ChannelRuleForm $form): void
    {
        $form->addElement('text', 'map_name', [
            'label'       => $form->translate('Map name'),
            'required'    => false,
            'description' => sprintf($form->translate(
                'Name of the map file without the .json suffix, stored in %s'
            ), self::getMapConfigDirectory())
        ]);
    }

    protected static function getMapConfigDirectory(): string
    {
        return dirname(Config::module('eventtracker')->getConfigFile()) . '/maps';
    }
}
