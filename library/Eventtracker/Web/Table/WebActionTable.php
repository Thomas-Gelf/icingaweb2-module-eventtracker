<?php

namespace Icinga\Module\Eventtracker\Web\Table;

use gipfl\IcingaWeb2\Link;
use Icinga\Module\Eventtracker\Web\WebAction;
use Ramsey\Uuid\Uuid;

class WebActionTable extends BaseTable
{
    /** @var WebAction */
    protected $action;

    public function __construct($db, WebAction $action)
    {
        $this->action = $action;
        parent::__construct($db);
    }

    protected function initialize()
    {
        $this->addAvailableColumns([
            $this->createColumn('label', $this->action->singular, ['label', 'uuid'])
                ->setRenderer(function ($row) {
                    return Link::create($row->label, $this->action->url, [
                        'uuid' => Uuid::fromBytes($row->uuid)->toString()
                    ]);
                }),
        ]);
    }

    public function prepareQuery()
    {
        return $this->db()
            ->select()
            ->from(['m' => $this->action->table], $this->getRequiredDbColumns())
            ->order('label');
    }
}
