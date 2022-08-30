<?php

namespace Icinga\Module\Eventtracker\Engine\Action;

use Icinga\Data\Filter\Filter;

trait ActionProperties
{
    /** @var bool */
    protected $enabled;

    /** @var Filter */
    protected $filter;

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setFilter($filter): self
    {
        if (! $filter instanceof Filter) {
            $filter = Filter::fromQueryString($filter);
        }
        $this->filter = $filter;

        return $this;
    }

    public function getFilter(): ?Filter
    {
        return $this->filter;
    }
}
