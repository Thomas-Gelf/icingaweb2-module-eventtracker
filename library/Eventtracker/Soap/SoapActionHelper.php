<?php

namespace Icinga\Module\Eventtracker\Soap;

use gipfl\ZfDb\Adapter\Adapter;
use Icinga\Module\Eventtracker\Db\ConfigStore;
use Icinga\Module\Eventtracker\Engine\Action\SoapAction;

class SoapActionHelper
{
    /**
     * @return SoapAction[]
     */
    public static function loadSoapActions(Adapter $db): array
    {
        return (new ConfigStore($db))->loadActions(['enabled' => 'y', 'implementation' => 'soap']);
    }
}
