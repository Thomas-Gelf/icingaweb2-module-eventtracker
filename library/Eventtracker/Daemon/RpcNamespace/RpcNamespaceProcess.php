<?php

namespace Icinga\Module\Eventtracker\Daemon\RpcNamespace;

use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;
use React\EventLoop\Loop;

class RpcNamespaceProcess implements EventEmitterInterface
{
    use EventEmitterTrait;

    const ON_RESTART = 'restart';

    /*
    public function infoRequest()
    {
        return $this->prepareProcessInfo($this->daemon);
    }

    protected function prepareProcessInfo(Daemon $daemon)
    {
        $details = $this->daemon->getProcessDetails()->getPropertiesToInsert();
        $details['process_info'] = \json_decode($details['process_info']);

        return (object) [
            'state'   => $this->daemon->getProcessState()->getInfo(),
            'details' => (object) $details,
        ];
    }
    */

    public function restartRequest(): bool
    {
        // Grant some time to ship the response
        Loop::addTimer(0.1, function () {
            $this->emit(self::ON_RESTART);
        });

        return true;
    }
}
