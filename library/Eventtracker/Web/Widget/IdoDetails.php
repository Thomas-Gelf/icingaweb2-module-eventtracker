<?php

namespace Icinga\Module\Eventtracker\Web\Widget;

use gipfl\Translation\TranslationHelper;
use Icinga\Module\Eventtracker\IcingaCi;
use Icinga\Module\Eventtracker\Issue;
use Icinga\Module\Monitoring\Backend\MonitoringBackend;
use Icinga\Module\Monitoring\Object\Host;
use Icinga\Module\Monitoring\Object\Service;
use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;

class IdoDetails extends BaseHtmlElement
{
    use TranslationHelper;

    protected $tag = 'div';

    protected $defaultAttributes = [
    ];

    /** @var Issue */
    protected $issue;

    /** @var MonitoringBackend */
    protected $backend;

    /** @var Host */
    protected $host;

    /** @var Service */
    protected $service;

    public function __construct(
        Issue $issue,
        \Zend_Db_Adapter_Abstract $db
    ) {
        $hostname = $issue->get('host_name');
        $objectName = $issue->get('object_name');
        $this->issue = $issue;
        $ido = MonitoringBackend::instance();
        if (IcingaCi::exists($db, $hostname, $objectName)) {
            $service = new Service($ido, $hostname, $objectName);
            $host = new Host($ido, $hostname);
            if ($service->fetch()) {
                $this->service = $service;
            }
            if ($host->fetch()) {
                $this->host = $host;
            }
        } elseif (IcingaCi::exists($db, $hostname)) {
            $ido = MonitoringBackend::instance();
            $host = new Host($ido, $hostname);
            if ($host->fetch()) {
                $this->host = $host;
            }
        }
    }

    protected function assemble()
    {
        $content = [
            Html::tag('h2', 'ICINGA'),
        ];
        $this->add(Html::tag('div', [
            'class' => 'output comment'
        ], $content));
    }
}
