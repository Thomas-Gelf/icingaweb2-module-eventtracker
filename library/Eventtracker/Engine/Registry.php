<?php

namespace Icinga\Module\Eventtracker\Engine;

interface Registry
{
    public function getInstance(string $identifier);

    public function getClassName(string $identifier): string;

    public function listImplementations(): array;
}
