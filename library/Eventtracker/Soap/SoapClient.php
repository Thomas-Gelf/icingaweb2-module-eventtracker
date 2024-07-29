<?php

namespace Icinga\Module\Eventtracker\Soap;

use SoapClient as PhpSoapClient;

class SoapClient extends PhpSoapClient
{
    public function __construct(string $wsdlUrl, string $username, string $password)
    {
        $wsdlFile = $this->fetchAuthenticatedWsdlFile($wsdlUrl, $this->prepareWsdlContext($username, $password));
        parent::__construct($wsdlFile, [
            'trace'    => 1,
            'login'    => $username,
            'password' => $password,
        ]);
    }

    /**
     * @param resource $context
     */
    private function fetchAuthenticatedWsdlFile(string $wsdlUrl, $context): string
    {
        $wsdl = file_get_contents($wsdlUrl, false, $context);
        $key = sha1($wsdlUrl);
        $wsdlFile = SafeCacheDir::getDirectory() . "/discovery-$key.wsdl";
        // TODO: Validate before storing
        file_put_contents($wsdlFile, $wsdl);

        return $wsdlFile;
    }

    private function prepareWsdlContext(string $username, string $password)
    {
        return stream_context_create([
            'ssl' => [
                // set some SSL/TLS specific options
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
            ],
            'http' => [
                'header' => [
                    'Authorization: Basic ' . base64_encode(sprintf('%s:%s', $username, $password)),
                ]
            ]
        ]);
    }
}
