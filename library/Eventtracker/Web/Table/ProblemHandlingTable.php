<?php

namespace Icinga\Module\Eventtracker\Web\Table;

use gipfl\IcingaWeb2\Link;
use gipfl\Translation\TranslationHelper;
use ipl\Html\Html;
use Ramsey\Uuid\Uuid;

class ProblemHandlingTable extends WebActionTable
{
    use TranslationHelper;

    protected $searchColumns = [
        'label',
        'instruction_url',
    ];

    protected function initialize()
    {
        $labelColumns = ['label', 'uuid', 'instruction_url'];
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

                    return [
                        Link::create($row->label, $this->action->url, [
                            'uuid' => Uuid::fromBytes($row->uuid)->toString()
                        ], $attrs),
                        Html::tag('br'),
                        Html::tag('span', ['style' => 'font-style: italic; display: inline-block; height: 1.5em; max-width: 100%; 	white-space: nowrap; text-overflow: ellipsis; overflow: hidden;'], $row->instruction_url),
                    ];
                }),
        ]);
    }
}
