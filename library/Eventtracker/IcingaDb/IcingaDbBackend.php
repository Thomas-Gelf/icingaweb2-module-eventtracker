<?php

namespace Icinga\Module\Eventtracker\IcingaDb;

use Icinga\Exception\NotFoundError;
use Icinga\Module\Icingadb\Common\Auth;
use Icinga\Module\Icingadb\Common\Database;
use Icinga\Module\Icingadb\Model\Host;
use Icinga\Module\Icingadb\Model\Service;
use Icinga\Module\Icingadb\Redis\VolatileStateResults;
use ipl\Stdlib\Filter;

class IcingaDbBackend
{
    use Auth;
    use Database;

    public function getHost(string $hostname): Host
    {
        $query = Host::on($this->getDb())->with(['state']);
        $query
            ->setResultSetClass(VolatileStateResults::class)
            ->filter(Filter::equal('host.name', $hostname));

        $this->applyRestrictions($query);
        /** @var Host $host */
        $host = $query->first();
        if ($host === null) {
            throw new NotFoundError(t('Host not found'));
        }

        return $host;
    }

    public function getService(string $hostname, string $serviceName): Service
    {
        $query = Service::on($this->getDb())->with(['state', 'host', 'host.state']);
        $query
            ->setResultSetClass(VolatileStateResults::class)
            ->filter(Filter::all(
                Filter::equal('host.name', $hostname),
                Filter::equal('service.name', $serviceName),
            ));

        $this->applyRestrictions($query);

        /** @var Service $service */
        $service = $query->first();
        if ($service === null) {
            throw new NotFoundError(t('Service not found'));
        }

        return $service;
    }

    /**
     * @param string $hostname
     * @param ?string $serviceName
     * @return Host|Service
     * @throws NotFoundError
     */
    public function getObject(string $hostname, string $serviceName = null)
    {
        if ($serviceName === null) {
            return $this->getHost($hostname);
        }

        return $this->getService($hostname, $serviceName);
    }
}
