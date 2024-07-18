<?php

namespace Icinga\Module\Eventtracker\Web\Table;

class InputSummaryTable extends BaseSummaryTable
{
    protected $searchColumns = [
        'inp.label',
    ];

    protected function getMainColumn(): string
    {
        return 'inp.label';
    }

    protected function getMainColumnTitle(): string
    {
        return $this->translate('Input');
    }

    public function prepareQuery()
    {
        $query = parent::prepareQuery();
        $query->joinLeft(['inp' => 'input'], 'i.input_uuid = inp.uuid', ['input_label' => 'inp.label']);
        return $query;
    }
}
