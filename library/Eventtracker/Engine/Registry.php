<?php

namespace Icinga\Module\Eventtracker\Engine;

interface Registry
{
    /**
     * @param string $identifier
     * @return object
     */
    public function getInstance($identifier);

    public function getClassName($identifier);

    public function listImplementations();
}
