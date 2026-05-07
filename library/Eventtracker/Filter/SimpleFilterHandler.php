<?php

namespace Icinga\Module\Eventtracker\Filter;

use gipfl\ZfDb\Select;
use Icinga\Web\UrlParams;
use ipl\Html\Table;

class SimpleFilterHandler
{

    private UrlParams $params;
    protected array $appliedFilters = [];
    public function __construct(UrlParams $params)
    {
        $this->params = $params;
    }

    public function applyToTable(Table $table)
    {
        $table->search($this->params->get('q'));
        $this->prepareFilterQuery($table, 'host_name');
        $this->prepareFilterQuery($table, 'object_name');
        $this->prepareFilterQuery($table, 'owner');
        $this->prepareFilterQuery($table, 'label');
        $this->prepareFilterQuery($table, 'sender_name');
        $this->attributesFilter($table->getQuery());
    }

    protected function prepareFilterQuery($table, $column)
    {
        if ($this->params->has($column)) {
            $value = $this->params->get($column);
            $this->appliedFilters[$column] = $value;
            if ($column === 'sender_name') {
                $table->joinSenders();
                $column = "s.$column";
            }
            if ($column === 'label') {
                $table->joinInputs();
                $column = "inp.$column";
            }
            $query = $table->getQuery();
            if (strlen($value)) {
                if (substr($value, 0, 1) === '!') {
                    $query->where("$column != ?", substr($value, 1));
                } elseif (false !== strpos($value, ',')) {
                    $query->where("$column IN (?)", explode(',', $value));
                } else {
                    $query->where("$column = ?", $value);
                }
            } else {
                $query->where("$column IS NULL");
            }
        }
    }

    protected function attributesFilter(Select $query)
    {
        foreach ($this->params->toArray() as $pair) {
            if (preg_match('/^attributes\.([a-zA-Z_-]+)/', $pair[0], $match)) {
                $query->where("JSON_EXTRACT(attributes, '$." . $match[1] . "') = ?", $pair[1]);
            }
        }
    }
}
