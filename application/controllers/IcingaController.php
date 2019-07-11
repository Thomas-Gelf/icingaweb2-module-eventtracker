<?php

namespace Icinga\Module\Eventtracker\Controllers;

use gipfl\IcingaWeb2\CompatController;
use Icinga\Exception\ConfigurationError;
use Icinga\Module\Monitoring\Backend\MonitoringBackend;
use ipl\Html\Html;

class IcingaController extends CompatController
{
    protected $requiresAuthentication = false;

    protected $monitoringDb;

    public function init()
    {
        if (! $this->isSsl()) {
            $this->deny('SSL is required');
        }
        if (! $this->isValidSslCertificate()) {
            $cn = $this->getSslCn();
            if ($cn === null) {
                $this->deny('Got no SSL CN');
            } else {
                $this->deny("SSL CN '$cn' is not allowed to access this resource");
            }
        }
    }

    protected function hasObject($name)
    {
        if (\strpos($name, '!') === false) {
            return $this->hasHost($name);
        } else {
            list($host, $service) = \preg_split('/\!/', $name, 2);
            return $this->hasService($host, $service);
        }
    }

    protected function hasHost($host)
    {
        $db = $this->monitoringDb();
        $select = $db->select()
            ->from(['o' => 'icinga_objects'], 'o.name1')
            ->where('o.name1 = ?', $host)
            ->where('o.objecttype_id = 1')
            ->where('o.is_active = 1');

        $result = $db->fetchOne($select);

        return $result === $host;
    }

    protected function hasService($host, $service)
    {
        $db = $this->monitoringDb();
        $select = $db->select()
            ->from(['o' => 'icinga_objects'], 'o.name2')
            ->where('o.name1 = ?', $host)
            ->where('o.name2 = ?', $service)
            ->where('o.objecttype_id = 2')
            ->where('o.is_active = 1');

        $result = $db->fetchOne($select);

        return $result === $service;
    }

    protected function getObjectState($name)
    {
        if (\strpos($name, '!') === false) {
            return $this->getHostState($name);
        } else {
            list($host, $service) = \preg_split('/\!/', $name, 2);
            return $this->getServiceState($host, $service);
        }
    }

    protected function getHostState($host)
    {
        $db = $this->monitoringDb();
        $select = $db->select()->from(['o' => 'icinga_objects'], [
            'host'         => 'o.name1',
            'state'        => "(CASE hs.current_state WHEN 0 THEN 'up' WHEN 1 THEN 'down' WHEN 2 THEN 'unreachable' ELSE 'pending' END)",
            'in_downtime'  => "(CASE WHEN hs.scheduled_downtime_depth > 0 THEN 'yes' ELSE 'no' END)",
            'acknowledged' => "(CASE WHEN hs.problem_has_been_acknowledged = 0 THEN 'no' ELSE 'yes' END)",
            'output'       => 'hs.output',
        ])->join(
            ['hs' => 'icinga_hoststatus'],
            'o.object_id = hs.host_object_id AND o.is_active = 1',
            []
        )->where('o.name1 = ?', $host);

        $result = $db->fetchRow($select);
        if ($result) {
            return $result;
        } else {
            return false;
        }
    }

    protected function getServiceState($host, $service)
    {
        $db = $this->monitoringDb();
        $select = $db->select()->from(['o' => 'icinga_objects'], [
            'host'         => 'o.name1',
            'service'      => 'o.name2',
            'state'        => "(CASE ss.current_state WHEN 0 THEN 'ok' WHEN 1 THEN 'warning' WHEN 2 THEN 'critical' WHEN 3 THEN 'unknown' ELSE 'pending' END)",
            'in_downtime'  => "(CASE WHEN ss.scheduled_downtime_depth > 0 THEN 'yes' ELSE 'no' END)",
            'acknowledged' => "(CASE WHEN ss.problem_has_been_acknowledged = 0 THEN 'no' ELSE 'yes' END)",
            'output'       => 'ss.output',
        ])->join(
            ['ss' => 'icinga_servicestatus'],
            'o.object_id = ss.service_object_id AND o.is_active = 1',
            []
        )->where('o.name1 = ?', $host)->where('o.name2 = ?', $service);

        $result = $db->fetchRow($select);
        if ($result) {
            return $result;
        } else {
            return false;
        }
    }

    /**
     * @return \Zend_Db_Adapter_Pdo_Abstract
     */
    protected function monitoringDb()
    {
        if ($this->monitoringDb === null) {
            try {
                $this->monitoringDb = MonitoringBackend::instance()->getResource()->getDbAdapter();
            } catch (ConfigurationError $e) {
                throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
            }
        }

        return $this->monitoringDb;
    }

    protected function deny($message)
    {
        $this->fail(403, $message);
    }

    protected function fail($code, $message)
    {
        $this->showError($message);
        try {
            $this->getResponse()->setHttpResponseCode($code);
        } catch (\Zend_Controller_Response_Exception $e) {
            throw new \InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }
        $this->finish();
    }

    protected function finish()
    {
        $this->getResponse()->sendResponse();
        exit;
    }

    protected function showError($message)
    {
        echo Html::tag('error', $message);
    }

    public function statusAction()
    {
        $object = $this->params->get('object');
        if ($object === null) {
            $this->fail(400, "Parameter 'object' is missing");
        }
        $object = str_replace('+', ' ', $object);
        try {
            $result = $this->getObjectState($object);
            if ($result === false) {
                $this->fail(404, "No such object: $object");
            } else {
                $tag = Html::tag('result')->setSeparator("\r\n");
                foreach ((array) $result as $key => $value) {
                    $tag->add(Html::tag($key, $value));
                }
                echo $tag;
                $this->finish();
            }
        } catch (\Exception $e) {
            $this->fail(500, $e->getMessage());
        }
    }

    public function eventAction()
    {
        $request = $this->getRequest();
        if (! $request->isPost()) {
            $this->fail(400, 'Only POST is supported, got ' . $request->getMethod());
        }
        $object = $request->getPost('Object');
        $state = $request->getPost('State');
        $message = $request->getPost('Message');
        $path = $request->getPost('Path');
        if ($object === null) {
            $this->fail(400, "Parameter 'Object' is missing");
        }
        if ($state === null) {
            $this->fail(400, "Parameter 'State' is missing");
        }
        if ($message === null) {
            $this->fail(400, "Parameter 'Message' is missing");
        }
        try {
            $result = $this->hasObject($object);
            $tag = Html::tag('result')->setSeparator("\r\n");
            if ($result === false) {
                $tag->add(Html::tag('success', 'true'));
            } else {
                $tag->add(Html::tag('success', 'false'));
                $tag->add(Html::tag('reason', 'Object not found'));
            }
            echo $tag;
            $this->finish();
        } catch (\Exception $e) {
            $this->fail(500, $e->getMessage());
        }
    }

    protected function isSsl()
    {
        return $this->getRequest()->getServer('HTTPS') === 'on';
    }

    protected function getSslCn()
    {
        return $this->getRequest()->getServer('SSL_CLIENT_S_DN_CN');
    }

    protected function hasSslCn()
    {
        return $this->getSslCn() !== null;
    }

    protected function isValidSslCertificate()
    {
        $allowed = \preg_split(
            '/\s*,\s*/',
            $this->Config('api')->get('ssl', 'allow_cn', ''),
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        return \in_array($this->getSslCn(), $allowed, true);
    }
}