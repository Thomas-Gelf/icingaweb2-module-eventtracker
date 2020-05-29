<?php

namespace Icinga\Module\Eventtracker\Web\Widget;

use gipfl\IcingaWeb2\Link;
use gipfl\Translation\TranslationHelper;
use Icinga\Application\Config;
use Icinga\Application\Hook;
use Icinga\Module\Eventtracker\IcingaCi;
use Icinga\Module\Eventtracker\Issue;
use Icinga\Module\Monitoring\Backend\MonitoringBackend;
use Icinga\Module\Monitoring\Object\Host;
use Icinga\Module\Monitoring\Object\MonitoredObject;
use Icinga\Module\Monitoring\Object\Service;
use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;
use Zend_Db_Adapter_Abstract as ZfDb;

class IdoDetails extends BaseHtmlElement
{
    use TranslationHelper;

    protected $tag = 'div';

    protected $defaultAttributes = [
    ];

    /** @var Issue */
    protected $issue;

    /** @var ZfDb */
    protected $db;

    /** @var MonitoringBackend */
    protected $ido;

    /** @var Host */
    protected $host;

    /** @var Service */
    protected $service;

    /** @var \stdClass */
    protected $icingaCi;

    public function __construct(
        Issue $issue,
        ZfDb $db
    ) {
        $hostname = $issue->get('host_name');
        $objectName = $issue->get('object_name');
        $this->issue = $issue;
        $this->ido = MonitoringBackend::instance();
        $this->db = $db;
        $this->checkForObject($hostname, $objectName);
        if ($this->host === null && \strpos($hostname, '.') === false) {
            $this->eventuallyCheckForFqdn($hostname, $objectName);
        }
    }

    protected function eventuallyCheckForFqdn($hostname, $objectName = null)
    {
        $domain = \trim(Config::module('eventtracker')->get('ido-sync', 'search_domain'), '.');
        if ($domain) {
            $this->checkForObject("$hostname.$domain", $objectName);
        }
    }

    protected function checkForObject($hostname, $objectName = null)
    {
        if (! $hostname) {
            return;
        }
        $db = $this->db;
        $ido = $this->ido;

        if ($ci = IcingaCi::eventuallyLoad($db, $hostname, $objectName)) {
            $this->icingaCi = $ci;
            $service = new Service($ido, $hostname, $objectName);
            $host = new Host($ido, $hostname);
            if ($service->fetch()) {
                $this->service = $service;
            }
            if ($host->fetch()) {
                $this->host = $host;
            }
        } elseif ($ci = IcingaCi::eventuallyLoad($db, $hostname)) {
            $this->icingaCi = $ci;
            $host = new Host($ido, $hostname);
            if ($host->fetch()) {
                $this->host = $host;
            }
        }
    }

    protected function getHostLink()
    {
        return Link::create($this->host->getName(), 'monitoring/host/show', [
            'host' => $this->host->getName()
        ], [
            'data-base-target' => '_next'
        ]);
    }

    protected function getServiceLink()
    {
        return Link::create($this->service->getName(), 'monitoring/service/show', [
            'host'    => $this->host->getName(),
            'service' => $this->service->getName(),
        ], [
            'data-base-target' => '_next'
        ]);
    }

    protected function getHookActions($hookName, MonitoredObject $object)
    {
        $actions = [];
        foreach (Hook::all($hookName) as $hook) {
            $hookActions = $hook->getActionsForObject($object);
            if (! \is_array($hookActions)) {
                // TODO: instanceof Navigation
                continue;
            }
            foreach ($hook->getActionsForObject($object) as $label => $url) {
                $actions[] = Link::create($label, $url, null, [
                    'data-base-target' => '_next'
                ]);
            }
        }

        return $actions;
    }

    protected function assemble()
    {
        if ($ci = $this->icingaCi) {
            $details = new NameValuePreFormatted();
            $details->addNameValueRow(
                $this->translate('Host'),
                $this->getHostLink()
            );
            if ($ci->object_type === 'service') {
                $details->addNameValueRow(
                    $this->translate('Service'),
                    $this->getServiceLink()
                );
                $actions = $this->getHookActions('Monitoring\\ServiceActions', $this->service);
            } else {
                $actions = $this->getHookActions('Monitoring\\HostActions', $this->host);
            }
            $preActions = [];
            $first = true;
            foreach ($actions as $action) {
                if ($first) {
                    $first = false;
                } else {
                    $preActions[] = "\n";
                }
                $preActions[] = $action;
            }
            $details->addNameValueRow(
                $this->translate('Actions'),
                $preActions
            );
            $details->addAttributes([
                'style' => 'width: 50%; display: inline-block'
            ]);
            $details->addNameValuePairs((array) $ci->vars);

        } else {
            $details = $this->translate('No related host is known to Icinga');
        }

        $this->add(Html::tag('div', [
            'class' => 'output comment',
        ], [Html::tag('h2', 'ICINGA'), $details]));
    }
}
