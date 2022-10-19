<?php

namespace Icinga\Module\Eventtracker\Web\Table;

use gipfl\IcingaWeb2\Link;
use gipfl\Translation\TranslationHelper;
use Ramsey\Uuid\Uuid;

class ConfiguredBucketsTable extends BaseTable
{
    use TranslationHelper;

    protected function initialize()
    {
        $this->addAvailableColumns([
            $this->createColumn('label', $this->translate('Bucket'), ['label', 'uuid'])
                ->setRenderer(function ($row) {
                    return Link::create($row->label, 'eventtracker/configuration/bucket', [
                        'uuid' => Uuid::fromBytes($row->uuid)->toString()
                    ]);
                }),
        ]);
    }

    public function prepareQuery()
    {
        return $this->db()
            ->select()
            ->from(['b' => 'bucket'], $this->getRequiredDbColumns())
            ->order('label');
    }
}
