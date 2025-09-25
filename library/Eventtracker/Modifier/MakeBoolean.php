<?php

namespace Icinga\Module\Eventtracker\Modifier;

use gipfl\Translation\TranslationHelper;
use Icinga\Module\Eventtracker\Web\Form\ChannelRuleForm;
use InvalidArgumentException;
use ipl\Html\Html;
use ipl\Html\ValidHtml;

class MakeBoolean extends BaseModifier
{
    use TranslationHelper;

    protected static array $validStrings = [
        '0'     => false,
        'false' => false,
        'n'     => false,
        'no'    => false,
        '1'     => true,
        'true'  => true,
        'y'     => true,
        'yes'   => true,
    ];

    protected static ?string $name = 'Create a Boolean';

    protected function simpleTransform($value)
    {
        if ($value === false || $value === true) {
            return $value;
        }

        if ($value === 0) {
            return false;
        }

        if ($value === 1) {
            return true;
        }

        if (is_string($value)) {
            $value = strtolower($value);

            if (array_key_exists($value, self::$validStrings)) {
                return self::$validStrings[$value];
            }
        }

        switch ($this->settings->get('on_invalid')) {
            case 'null':
                return null;

            case 'false':
                return false;

            case 'true':
                return true;

            case 'fail':
            default:
                throw new InvalidArgumentException(
                    '"%s" cannot be converted to a boolean value',
                    $value
                );
        }
    }

    public static function extendSettingsForm(ChannelRuleForm $form): void
    {
        $form->addElement('select', 'on_invalid', [
            'label'       => $form->translate('When missing'),
            'required'    => true,
            'description' => $form->translate(
                "'0', 'false', 'n' and 'no' will become false. '1', 'true', 'y' and 'yes' will become true."
                . " What should happen in case another value appears?"
            ),
            'options'     => [
                null      => $form->translate('- please choose -'),
                'null'    => $form->translate('Set null'),
                'false' => $form->translate('Set to false'),
                'true'    => $form->translate('Set to true'),
                'fail'    => $form->translate('Let the action fail'),
            ]
        ]);
    }

    public function describe(string $propertyName): ValidHtml
    {
        return Html::sprintf(
            $this->translate('Convert %s into a boolean value'),
            Html::tag('strong', $propertyName),
        );
    }
}
