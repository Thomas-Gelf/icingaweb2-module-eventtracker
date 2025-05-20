<?php

namespace Icinga\Module\Eventtracker\Daemon\RpcNamespace;

use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;
use Icinga\Module\Eventtracker\Engine\Downtime\DowntimeRunner;
use Psr\Log\LoggerInterface;

class RpcNamespaceEventtracker implements EventEmitterInterface
{
    use EventEmitterTrait;

    protected DowntimeRunner $downtimeRunner;
    protected LoggerInterface $logger;

    public function __construct(
        DowntimeRunner $downtimeRunner,
        LoggerInterface $logger
    ) {
        $this->downtimeRunner = $downtimeRunner;
        $this->logger = $logger;
    }

    /**
     * @api
     */
    public function getActivePeriodsRequest()
    {
        return $this->downtimeRunner->periodRunner->getActiveRules();
    }

    /**
     * @api
     */
    public function getActiveTimeSlotsRequest()
    {
        return $this->downtimeRunner->periodRunner->getActiveTimeSlots();
    }

    /**
     * @api
     */
    public function reloadDowntimeRulesRequest(): bool
    {
        $this->downtimeRunner->periodRunner->recheckDowntimeRules();

        return true;
    }

    /**
     * @api
     */
    public function reloadHostListsRequest(): bool
    {
        if ($store = $this->downtimeRunner->store) {
            $store->forgetHostLists();
        }

        return true;
    }
}
