<?php

namespace Icinga\Module\Eventtracker\Modifier;

use gipfl\Json\JsonString;
use gipfl\Translation\TranslationHelper;
use Icinga\Module\Eventtracker\Web\Form\ChannelRuleForm;
use InvalidArgumentException;
use ipl\Html\Html;
use ipl\Html\ValidHtml;

class DownloadUrl extends BaseModifier
{
    use TranslationHelper;

    protected static ?string $name = 'Download a specific URL';

    protected function simpleTransform($value)
    {
        $parts = parse_url($value);
        if ($parts === false) {
            throw new InvalidArgumentException('URL expected, got ' . JsonString::encode($value));
        }

        $this->assertSupportedScheme($parts['scheme']);

        if ($parts['scheme'] === 'https') {
            $context = [
                'ssl' => [
                    'verify_peer'       => true,
                    'verify_peer_name'  => true,
                ]
            ];

            $sslParameterMap = [
                'ssl_ca'   => 'cafile',
                'ssl_key'  => 'local_pk',
                'ssl_cert' => 'local_cert',
            ];

            foreach ($sslParameterMap as $setting => $parameter) {
                if ($settingValue = $this->settings->get($setting)) {
                    $context['ssl'][$parameter] = $settingValue;
                }
            }
        } else {
            $context = [];
        }

        if ($header = $this->settings->get('header')) {
            $context['http']['header'] = $header;
        }

        try {
            return file_get_contents($value, false, stream_context_create($context));
        } catch (\Exception $e) {
            return $e->getMessage() . "\r\n" . print_r($context, 1);
        }
    }

    protected function assertSupportedScheme($scheme)
    {
        if (! in_array(strtolower($scheme), ['http', 'https'])) {
            throw new InvalidArgumentException("Valid scheme expected, got '$scheme'");
        }
    }

    public function describe(string $propertyName): ValidHtml
    {
        return Html::sprintf(
            $this->translate('Replace %s with content downloaded from the URL it contains'),
            Html::tag('strong', $propertyName),
            Html::tag('strong', $propertyName),
        );
    }

    public static function extendSettingsForm(ChannelRuleForm $form): void
    {
        $form->addElement('textarea', 'header', [
            'label'       => $form->translate('Additional HTTP Header'),
            'required'    => false,
            'description' => $form->translate('Use this in case you want to provide additional HTTP Headers'),
        ]);
        $form->addElement('text', 'ssl_ca', [
            'label'       => $form->translate('SSL CA file'),
            'required'    => false,
            'description' => $form->translate('Path to your (optional) trusted CA file'),
        ]);
        $form->addElement('text', 'ssl_cert', [
            'label'       => $form->translate('SSL Certificate file'),
            'required'    => false,
            'description' => $form->translate('Path to your (optional) SSL Certificate file'),
        ]);
        $form->addElement('text', 'ssl_key', [
            'label'       => $form->translate('SSL Key file'),
            'required'    => false,
            'description' => $form->translate('Path to your (optional) SSL Key file'),
        ]);
    }
}
