<?php

namespace Icinga\Module\Eventtracker\Web\Table;

use gipfl\IcingaWeb2\Link;
use gipfl\Translation\TranslationHelper;
use Icinga\Module\Eventtracker\Web\Widget\ActiveDowntimeSlots;
use Icinga\Module\Eventtracker\Web\Widget\DowntimeDescription;
use ipl\Html\Html;
use Ramsey\Uuid\Uuid;

class DowntimeRulesTable extends WebActionTable
{
    use ActiveDowntimeSlots;
    use TranslationHelper;

    protected function initialize()
    {
        $this->addAvailableColumns([
            $this->createColumn('label', $this->action->singular, [
                'uuid'        => 'dr.uuid',
                'label'       => 'dr.label',
                'message'     => 'dr.message',
                'is_active'    => "CASE WHEN dr.ts_triggered IS NULL THEN 'n' ELSE 'y' END",
                'ts_triggered' => "dr.ts_triggered",
            ])
            ->setRenderer(function ($row) {
                $uuid = Uuid::fromBytes($row->uuid);
                $label = Html::tag('strong', $row->label);

                return [
                    Link::create($label, $this->action->url, [
                        'uuid' => $uuid->toString()
                    ]),
                    Html::tag('br'),
                    DowntimeDescription::getDowntimeActiveInfo(
                        $this->getActiveDowntimeSlot($uuid),
                        $row->ts_triggered
                    ),
                    Html::tag('div', ['style' => 'font-style: italic'], $row->message)
                ];
            }),
        ]);
    }

    public function prepareQuery()
    {
        return $this->db()
            ->select()
            ->from(['dr' => 'downtime_rule'], $this->getRequiredDbColumns())
            ->order('label');
    }
}
