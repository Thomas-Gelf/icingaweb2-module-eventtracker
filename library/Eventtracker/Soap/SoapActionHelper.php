<?php

namespace Icinga\Module\Eventtracker\Soap;

use gipfl\ZfDb\Adapter\Pdo\PdoAdapter;
use Icinga\Module\Eventtracker\Db\ConfigStore;
use Icinga\Module\Eventtracker\Engine\Action\SoapAction;

class SoapActionHelper
{
    /**
     * @return SoapAction[]
     */
    public static function loadSoapActions(PdoAdapter $db): array
    {
        /** @var SoapAction[] $actions */
        $actions = (new ConfigStore($db))->loadActions(['enabled' => 'y', 'implementation' => 'soap']);
        return $actions;
    }
}
