<?php

namespace Icinga\Module\Eventtracker\Web\Table;

use gipfl\IcingaWeb2\Link;
use Icinga\Module\Eventtracker\Web\WebAction;
use Ramsey\Uuid\Uuid;

class WebActionTable extends BaseTable
{
    /** @var WebAction */
    protected $action;
    protected $hasColumnEnabled = false;

    public function __construct($db, WebAction $action)
    {
        $this->action = $action;
        parent::__construct($db);
    }

    protected function initialize()
    {
        $labelColumns = ['label', 'uuid'];
        if ($this->hasColumnEnabled) {
            $labelColumns[] = 'enabled';
        }
        $this->addAvailableColumns([
            $this->createColumn('label', $this->action->singular, $labelColumns)
                ->setRenderer(function ($row) {
                    if ($this->hasColumnEnabled && $row->enabled === 'n') {
                        $attrs = ['style' => 'font-style: italic'];
                    } else {
                        $attrs = [];
                    }
                    return Link::create($row->label, $this->action->url, [
                        'uuid' => Uuid::fromBytes($row->uuid)->toString()
                    ], $attrs);
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
