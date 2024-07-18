<?php

namespace Icinga\Module\Eventtracker\Web\Table;

use gipfl\Format\LocalDateFormat;
use gipfl\Format\LocalTimeFormat;
use gipfl\IcingaWeb2\Link;
use gipfl\Translation\TranslationHelper;
use ipl\Html\Html;
use Ramsey\Uuid\Uuid;

class DowntimeRulesTable extends WebActionTable
{
    use TranslationHelper;

    /** @var LocalTimeFormat */
    protected $timeFormatter;

    /** @var LocalDateFormat */
    protected $dateFormatter;

    protected function initialize()
    {
        $this->timeFormatter = new LocalTimeFormat();
        // $this->timeFormatter->setTimezone(new \DateTimeZone('Europe/Berlin'));
        $this->dateFormatter = new LocalDateFormat();
        $this->addAvailableColumns([
            $this->createColumn('label', $this->action->singular, [
                'uuid'        => 'dr.uuid',
                'label'       => 'dr.label',
                'message'     => 'dr.message',
                'is_active'         => 'dc.is_active',
                'ts_started'        => 'dc.ts_started',
                'ts_expected_start' => 'dc.ts_expected_start',
                'ts_expected_end'   => 'dc.ts_expected_end',
            ])
            ->setRenderer(function ($row) {
                $label = Html::tag('strong', $row->label);
                if ($row->is_active === null) {
                    $activeInfo = $this->translate('Currently no downtime has been scheduled for this rule');
                } elseif ($row->is_active === 'y') {
                    $activeInfo = sprintf(
                        $this->translate('This Downtime is active since %s and will end %s'),
                        $this->niceTsFormat($row->ts_started),
                        $this->niceTsFormat($row->ts_expected_end)
                    );
                } else {
                    $activeInfo = sprintf(
                        $this->translate('Next activation: %s'),
                        $this->niceTsFormat($row->ts_expected_start)
                    );
                }
                return [
                    Link::create($label, $this->action->url, [
                        'uuid' => Uuid::fromBytes($row->uuid)->toString()
                    ]),
                    Html::tag('br'),
                    $activeInfo,
                    Html::tag('div', ['style' => 'font-style: italic'], $row->message)
                ];
            }),
        ]);
    }

    protected function niceTsFormat($ts): string
    {
        $ts = $ts / 1000;
        return $this->dateFormatter->getFullDay($ts) . ' ' . $this->timeFormatter->getShortTime($ts);
    }

    public function prepareQuery()
    {
        return $this->db()
            ->select()
            ->from(['dr' => 'downtime_rule'], $this->getRequiredDbColumns())
            ->joinLeft(['dc' => 'downtime_calculated'], 'dr.next_calculated_uuid = dc.uuid', [])
            ->order('label');
    }
}
