<?php

namespace Icinga\Module\Eventtracker\Daemon;

use gipfl\ZfDb\Adapter\Pdo\PdoAdapter;
use React\Promise\PromiseInterface;

interface DbBasedComponent
{
    public function initDb(PdoAdapter $db): void;

    public function stopDb(): PromiseInterface;
}
