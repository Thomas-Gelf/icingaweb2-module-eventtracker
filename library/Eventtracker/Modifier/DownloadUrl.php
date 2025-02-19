<?php

namespace Icinga\Module\Eventtracker\Modifier;

use gipfl\Json\JsonString;
use InvalidArgumentException;

class DownloadUrl extends BaseModifier
{
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
}
