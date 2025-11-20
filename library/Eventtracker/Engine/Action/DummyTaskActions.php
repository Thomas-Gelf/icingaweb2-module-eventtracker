<?php

namespace Icinga\Module\Eventtracker\Engine\Action;

use React\EventLoop\LoopInterface;

trait DummyTaskActions
{
    public function run(): void
    {
        $this->start();
    }

    public function start(): void
    {
        $this->resume();
    }

    public function stop(): void
    {
        $this->pause();
    }

    public function pause(): void
    {
        $this->paused = true;
    }

    public function resume(): void
    {
        $this->paused = false;
    }
}
