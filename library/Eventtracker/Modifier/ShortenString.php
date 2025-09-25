<?php

namespace Icinga\Module\Eventtracker\Modifier;

use gipfl\Translation\TranslationHelper;
use Icinga\Module\Eventtracker\Web\Form\ChannelRuleForm;
use InvalidArgumentException;
use ipl\Html\Html;

class ShortenString extends BaseModifier
{
    use TranslationHelper;

    protected static ?string $name = 'Shorten String';

    protected function simpleTransform($value)
    {
        $strip = $this->settings->get('strip', 'ending');
        $length = $this->settings->getRequired('max_length');
        if (strlen($value) < $length) {
            return $value;
        }

        switch ($strip) {
            case 'ending':
                return substr($value, 0, $length);
            case 'beginning':
                return substr($value, -1 * $length);
            case 'center':
                $concat = ' ... ';
                $availableLength = ($length - strlen($concat)) / 2;
                return substr($value, 0, ceil($availableLength)) . $concat . substr($value, floor($availableLength));
            default:
                throw new InvalidArgumentException('strip="$strip" is not a valid setting');
        }
    }

    public function describe(string $propertyName)
    {
        return Html::sprintf(
            $this->translate('Shorten String in %s to %s characters, truncate the %s'),
            Html::tag('strong', $propertyName),
            $this->settings->getRequired('max_length'),
            $this->describeStrippedPart()
        );
    }

    protected function describeStrippedPart(): string
    {
        switch ($this->settings->get('strip', 'ending')) {
            case 'ending':
                return $this->translate('the ending');
            case 'beginning':
                return $this->translate('the beginning');
            case 'center':
                return $this->translate('the center');
            default:
                throw new InvalidArgumentException('strip="$strip" is not a valid setting');
        }
    }

    public static function extendSettingsForm(ChannelRuleForm $form): void
    {
        $form->addElement('text', 'max_length', [
            'label'       => $form->translate('Maximum length'),
            'required'    => true,
            'description' => $form->translate('Maximum allowed string length, longer ones will be shortened')
        ]);
        $form->addElement('select', 'strip', [
            'label'       => $form->translate('Strip'),
            'required'    => false,
            'description' => $form->translate('Which part to strip from a longer string (defaults to "ending")'),
            'options'     => [
                null        => $form->translate('- please choose -'),
                'ending'    => $form->translate('Strip the ending'),
                'beginning' => $form->translate('Strip the beginning'),
                'center'    => $form->translate('Strip the center/middle part'),
            ]
        ]);
    }
}
