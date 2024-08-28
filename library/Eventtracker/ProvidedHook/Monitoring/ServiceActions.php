<?php

namespace Icinga\Module\Eventtracker\ProvidedHook\Monitoring;

use Icinga\Authentication\Auth;
use Icinga\Module\Eventtracker\DbFactory;
use Icinga\Module\Eventtracker\Soap\SoapActionHelper;
use Icinga\Module\Monitoring\Hook\ServiceActionsHook;
use Icinga\Module\Monitoring\Object\Service;
use Icinga\Web\Url;

class ServiceActions extends ServiceActionsHook
{
    /**
     * @param Service $service
     * @return array
     * @throws \Icinga\Exception\ProgrammingError
     */
    public function getActionsForService(Service $service)
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
                    'host'    => $service->host_name,
                    'service' => $service->service_description,
                    'source'  => 'icingadb',
                    'action'  => $action->getUuid()->toString()
                ]);
            }
        }

        return $urls;
    }
}
