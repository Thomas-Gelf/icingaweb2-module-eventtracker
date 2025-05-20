<?php

namespace Icinga\Module\Eventtracker\Engine\Action;

use React\EventLoop\LoopInterface;

trait DummyTaskActions
{
    public function run()
    {
        $this->start();
    }

    public function start()
    {
        $this->resume();
    }

    public function stop()
    {
        $this->pause();
    }

    public function pause()
    {
        $this->paused = true;
    }

    public function resume()
    {
        $this->paused = false;
    }
}
