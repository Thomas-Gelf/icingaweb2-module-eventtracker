<?php

namespace Icinga\Module\Eventtracker\Modifier;

use gipfl\Translation\TranslationHelper;
use Icinga\Module\Eventtracker\Web\Form\ChannelRuleForm;
use InvalidArgumentException;
use ipl\Html\Html;
use ipl\Html\ValidHtml;
use RuntimeException;

use function gettype;
use function is_object;
use function property_exists;

class MapLookup extends BaseModifier
{
    use TranslationHelper;
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

    public function describe(string $propertyName): ValidHtml
    {
        switch ($this->settings->get('when_missing', 'null')) {
            case 'null':
                $whenMissing = $this->translate('set null');
                break;
            case 'default':
                $whenMissing = 'set to "' . $this->settings->getRequired('default_value') . '"';
                break;
            case 'keep':
                $whenMissing = 'keep as is';
                break;
            case 'fail':
                $whenMissing = 'throw an error';
                break;
            default:
                $whenMissing = '(invalid action)';
        }

        return Html::sprintf(
            $this->translate('Look up %s in a map, when not found %s'),
            Html::tag('strong', $propertyName),
            $whenMissing
        );
    }

    public static function extendSettingsForm(ChannelRuleForm $form): void
    {
        $form->addElement('textarea', 'map', [
            'label'       => $form->translate('Map'),
            'required'    => false,
            'description' => $form->translate('Your key/value translation map')
        ]);
        $form->addElement('select', 'when_missing', [
            'label'       => $form->translate('When missing'),
            'required'    => true,
            'description' => $form->translate('What to to, when your value is not to be found in this map'),
            'class'       => 'autosubmit',
            'options'     => [
                null      => $form->translate('- please choose -'),
                'null'    => $form->translate('Set null'),
                'default' => $form->translate('Set a default value'),
                'keep'    => $form->translate('Keep the given value'),
                'fail'    => $form->translate('Let the action fail'),
            ]
        ]);
        if ($form->getValue('when_missing') === 'default') {
            $form->addElement('text', 'default_value', [
                'label'    => $form->translate('Default Value'),
                'required' => false,
            ]);
        } else {
            $form->addHidden('default_value', null);
        }
    }
}
