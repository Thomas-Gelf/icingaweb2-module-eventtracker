<?php

namespace Icinga\Module\Eventtracker\Daemon;

use gipfl\ZfDb\Adapter\Adapter as Db;

interface DbBasedComponent
{
    /**
     * @param Db $db
     * @return \React\Promise\ExtendedPromiseInterface;
     */
    public function initDb(Db $db);

    /**
     * @return \React\Promise\ExtendedPromiseInterface;
     */
    public function stopDb();
}
