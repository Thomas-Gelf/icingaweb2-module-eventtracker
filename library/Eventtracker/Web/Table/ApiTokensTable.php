<?php

namespace Icinga\Module\Eventtracker\Web\Table;

use gipfl\IcingaWeb2\Link;
use gipfl\Translation\TranslationHelper;
use Ramsey\Uuid\Uuid;

class ApiTokensTable extends BaseTable
{
    use TranslationHelper;

    protected function initialize()
    {
        $this->addAvailableColumns([
            $this->createColumn('label', $this->translate('Label'), 'label')
                ->setRenderer(function ($row) {
                    return Link::create($row->label, 'eventtracker/configuration/apitoken', [
                        'uuid' => Uuid::fromBytes($row->uuid)->toString()
                    ]);
                }),
        ]);
    }

    public function prepareQuery()
    {
        $query = $this->db()
            ->select()
            ->from(['t' => 'api_token'], [
                'uuid',
                'label',
            ])->order('label');

        return $query;
    }
}
