<?php

namespace Icinga\Module\Eventtracker\Modifier;

use Icinga\Module\Eventtracker\Web\Form\ChannelRuleForm;
use InvalidArgumentException;
use RuntimeException;
use function gettype;
use function is_object;
use function property_exists;

class MapLookup extends BaseModifier
{
    protected static ?string $name = 'Lookup (and map) values';

    protected $map;

    protected function simpleTransform($value)
    {
        $map = $this->getMap();
        if (property_exists($map, $value)) {
            return $map->$value;
        }

        switch ($this->settings->get('when_missing', 'null')) {
            case 'null':
                return null;
            case 'default':
                return $this->settings->getRequired('default_value');
            case 'keep':
                return $value;
            case 'fail':
                throw new InvalidArgumentException("'$value' is not part of the configured map");
            default:
                throw new RuntimeException(
                    $this->settings->get('when_missing', 'null')
                    . 'is not a valid Map fallback action'
                );
        }
    }

    protected function getMap()
    {
        if ($this->map === null) {
            $this->map = $this->loadMap();
        }

        return $this->map;
    }

    protected function loadMap()
    {
        $map = $this->settings->getRequired('map');
        if (! is_object($map)) {
            throw new RuntimeException('Map required, got ' . gettype($map));
        }

        return $map;
    }

    public static function extendSettingsForm(ChannelRuleForm $form): void
    {
        $form->addElement('text', 'default_value', [
            'label' => $form->translate('Default Value'),
            'required' => false,
            'description' => "default value"
        ]);
        $form->addElement('text', 'map', [
            'label' => $form->translate('Map'),
            'required' => false,
            'description' => "map"
        ]);

        $form->addElement('text', 'when_missing', [
            'label' => $form->translate('When missing'),
            'required' => false,
            'description' => "when missing"
        ]);
    }
}
