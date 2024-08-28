<?php

namespace Icinga\Module\Eventtracker\ProvidedHook\Monitoring;

use Icinga\Authentication\Auth;
use Icinga\Module\Eventtracker\DbFactory;
use Icinga\Module\Eventtracker\Soap\SoapActionHelper;
use Icinga\Module\Monitoring\Hook\HostActionsHook;
use Icinga\Module\Monitoring\Object\Host;
use Icinga\Web\Url;

class HostActions extends HostActionsHook
{
    /**
     * @param Host $host
     * @return array
     * @throws \Icinga\Exception\ProgrammingError
     */
    public function getActionsForHost(Host $host)
    {
        $urls = [];
        if (!Auth::getInstance()->hasPermission('eventtracker/operator')) {
            return [];
        }
        $db = DbFactory::db();
        $actions = SoapActionHelper::loadSoapActions($db);
        foreach ($actions as $action) {
            if ($label = $action->getSettings()->get('icingaActionHook')) {
                $urls[$label] = Url::fromPath('eventtracker/action/run', [
                    'host' => $host->host_name,
                    'source' => 'icingadb',
                    'action' => $action->getUuid()->toString(),
                ]);
            }
        }

        return $urls;
    }
}
