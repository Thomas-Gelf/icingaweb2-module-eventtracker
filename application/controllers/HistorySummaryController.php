<?php

namespace Icinga\Module\Eventtracker\Controllers;

use gipfl\ZfDb\Select;

class HistorySummaryController extends SummaryController
{
    protected string $tableName = 'issue_history';

    protected bool $isHistory = true;

    #[\Override]
    protected function getTitleForType(string $type): string
    {
        return sprintf($this->translate('Issue History Summary by %s'), $type);
    }

    #[\Override]
    protected function getTop10Title(): string
    {
        return sprintf($this->translate('Top Issue History Summary by:'));
    }

    #[\Override]
    protected function applyStatusLimit(Select $select): void
    {
    }

    protected function showCompact()
    {
        return $this->params->get('view') === 'compact';
    }
}
