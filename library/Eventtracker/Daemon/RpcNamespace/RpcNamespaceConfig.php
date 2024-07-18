<?php

namespace Icinga\Module\Eventtracker\Daemon\RpcNamespace;

use Icinga\Module\Eventtracker\Daemon\RunningConfig;

class RpcNamespaceConfig
{
    /** @var RunningConfig */
    protected $config;

    public function __construct(RunningConfig $config)
    {
        $this->config = $config;
    }

    /**
     * @api
     * @return ?bool
     */
    public function reloadDowntimeRulesRequest(): ?bool
    {
        return $this->config->reloadDowntimeRules();
    }
}
