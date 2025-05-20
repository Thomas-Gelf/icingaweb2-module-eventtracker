<?php

namespace Icinga\Module\Eventtracker\Engine\Bucket;

use Evenement\EventEmitterInterface;
use Icinga\Module\Eventtracker\Engine\Task;
use Icinga\Module\Eventtracker\Event;

interface BucketInterface extends EventEmitterInterface, Task
{
    public function processEvent(Event $event): ?Event;
}
