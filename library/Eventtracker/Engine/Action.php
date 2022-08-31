<?php

namespace Icinga\Module\Eventtracker\Engine;

use Icinga\Data\Filter\Filter;
use Icinga\Module\Eventtracker\Issue;
use React\Promise\PromiseInterface;

interface Action extends Task
{
    public function setEnabled(bool $enabled);

    public function isEnabled(): bool;

    public function setFilter($filter);

    public function getFilter(): ?Filter;

    public function process(Issue $issue): PromiseInterface;
}
