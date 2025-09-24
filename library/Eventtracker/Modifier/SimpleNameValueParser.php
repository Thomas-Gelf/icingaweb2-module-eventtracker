<?php

namespace Icinga\Module\Eventtracker\Modifier;

use Icinga\Module\Eventtracker\Web\Form\ChannelRuleForm;
use function ipl\Stdlib\get_php_type;

class SimpleNameValueParser extends BaseModifier
{
    protected static ?string $name = 'Parse "key=value" strings';

    protected function simpleTransform($value)
    {
        if (! is_string($value)) {
            throw new \InvalidArgumentException(
                "SimpleNameValueParser Expected string, got " . get_php_type($value)
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

    public static function extendSettingsForm(ChannelRuleForm $form): void
    {
        $form->addElement(
            'text',
            'catchall_key',
            [
                'label' => $form->translate('catchall_key'),
                'required' => false,
                'description' => 'catchall_key'
            ]
        );
    }
}
