<?php

namespace Icinga\Module\Eventtracker\Controllers;

use gipfl\ZfDbStore\NotFoundError;
use Icinga\Application\Modules\Module;
use Icinga\Module\Eventtracker\DbFactory;
use Icinga\Module\Eventtracker\Engine\Action\SoapAction;
use Icinga\Module\Eventtracker\IcingaDb\IcingaDbBackend;
use Icinga\Module\Eventtracker\Soap\SoapActionHelper;
use Icinga\Module\Eventtracker\Soap\SoapInteractiveActionForm;
use Icinga\Module\Monitoring\Backend\MonitoringBackend;
use Icinga\Module\Monitoring\Object\Host;
use Icinga\Module\Monitoring\Object\Service;

class ActionController extends Controller
{
    use AsyncControllerHelper;

    public function runAction()
    {
        $this->addSingleTab($this->translate('Trigger Action'));
        $db = DbFactory::db();
        $actions = SoapActionHelper::loadSoapActions($db);
        $uuidString = $this->params->getRequired('action');
        if (! isset($actions[$uuidString])) {
            throw new NotFoundError($this->translate('There is no such action'));
        }

        $host = $this->params->getRequired('host');
        $service = $this->params->get('service');
        if (Module::exists('icingadb')) {
            $db = new IcingaDbBackend();
            if ($service) {
                $object = $db->getService($host, $service);
            } else {
                $object = $db->getHost($host);
            }
        } elseif (Module::exists('monitoring')) {
            if ($service) {
                $object = new Service(MonitoringBackend::instance(), $host, $service);
                $object->fetch();
                $object->fetchHostVariables();
                $object->fetchServiceVariables();
            } else {
                $object = new Host(MonitoringBackend::instance(), $host);
                $object->fetch();
                $object->fetchHostVariables();
            }
        } else {
            throw new NotFoundError('Neither icingadb nor monitoring is active');
        }


        $action = $actions[$uuidString];
        $this->addTitle($action->getName());
        if (! $action instanceof SoapAction) {
            throw new NotFoundError($this->translate('Triggering actions is available for SOAP actions only'));
        }
        $form = new SoapInteractiveActionForm($action, $object);
        $form->handleRequest($this->getServerRequest());
        $this->content()->add($form);
    }
}
