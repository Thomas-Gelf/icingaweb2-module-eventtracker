<?php

namespace Icinga\Module\Eventtracker\Engine\Action;

use Icinga\Data\Filter\Filter;

trait ActionProperties
{
    /** @var string */
    protected $description;

    /** @var bool */
    protected $enabled;

    /** @var Filter */
    protected $filter;

    public function getActionDescription(): ?string
    {
        return $this->description;
    }

    public function setActionDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

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
        if ($filter !== null && ! $filter instanceof Filter) {
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
