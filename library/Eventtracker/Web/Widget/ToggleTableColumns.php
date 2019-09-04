<?php

namespace Icinga\Module\Eventtracker\Web\Widget;

use gipfl\IcingaWeb2\Url;
use Icinga\Module\Eventtracker\Web\Table\BaseTable;

class ToggleTableColumns extends ToggleFlagList
{
    /** @var BaseTable */
    protected $table;

    public function __construct(BaseTable $table, Url $url)
    {
        parent::__construct($url, 'columns');
        $this->table = $table;
    }

    protected function getListLabel()
    {
        return $this->translate('Columns');
    }

    protected function getDefaultSelection()
    {
        return $this->table->getChosenColumnNames();
    }

    protected function setEnabled($enabled)
    {
        $this->table->chooseColumns($enabled);
    }

    protected function getOptions()
    {
        $options = [];
        foreach ($this->table->getAvailableColumns() as $column) {
            $title = $column->getTitle();
            $alias = $column->getAlias();
            $options[$alias] = $title;
        }

        return $options;
    }
}
