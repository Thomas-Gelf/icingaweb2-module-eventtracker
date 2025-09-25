<?php

namespace Icinga\Module\Eventtracker\Modifier;

use gipfl\Translation\TranslationHelper;
use Icinga\Module\Eventtracker\Web\Form\ChannelRuleForm;
use InvalidArgumentException;
use ipl\Html\Html;
use ipl\Html\ValidHtml;

class SimpleNameValueParser extends BaseModifier
{
    use TranslationHelper;

    protected static ?string $name = 'Parse "key=value" strings';

    protected function simpleTransform($value)
    {
        if (! is_string($value)) {
            throw new InvalidArgumentException(
                'SimpleNameValueParser Expected string, got ' . get_debug_type($value)
            );
        }
        $parts = explode(' ', $value);
        $properties = [];
        $key = null;
        $catchAll = $this->settings->get('catchall_key');

        // TODO: Improve this.
        foreach ($parts as $part) {
            if ($catchAll && $key === $catchAll) {
                $properties[$catchAll] .= " $part";
            } elseif (($pos = strpos($part, '=')) === false) {
                $key = 'invalid';
                if (isset($properties[$key])) {
                    $properties[$key] .= " $part";
                } else {
                    $properties[$key] = $part;
                }
            } else {
                $key = substr($part, 0, $pos);
                $properties[$key] = substr($part, $pos + 1);
            }
        }

        return (object) $properties;
    }

    public function describe(string $propertyName): ValidHtml
    {
        return Html::sprintf(
            $this->translate('Transform "key=value"-pairs in %s into a dictionary'),
            Html::tag('strong', $propertyName),
        );
    }

    public static function extendSettingsForm(ChannelRuleForm $form): void
    {
        $form->addElement('text', 'catchall_key', [
            'label'       => $form->translate('Catch-All key'),
            'required'    => false,
            'description' => $form->translate(
                'Everything after this key will make part of its value, regardless of other equal-signs (=).'
            )
        ]);
    }
}
