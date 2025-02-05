<?php

namespace Icinga\Module\Eventtracker\Soap;

class SoapConnection
{
    /** @var string */
    protected $wsdlUrl;

    /** @var string */
    protected $username;

    /** @var string */
    protected $password;

    public function __construct(string $wsdlUrl, string $username, string $password)
    {
        $this->wsdlUrl = $wsdlUrl;
        $this->username = $username;
        $this->password = $password;
    }


    public function fetchWsdl()
    {
        $serverId = $this->serverInfo->getServerId();
        $cacheDir = SafeCacheDir::getSubDirectory("wsdl-$serverId");
        $loader = new WsdlLoader($cacheDir, $this->logger, $this->serverInfo, $this->curl);
        return $loader->fetchInitialWsdlFile($this->loop);
    }
}
