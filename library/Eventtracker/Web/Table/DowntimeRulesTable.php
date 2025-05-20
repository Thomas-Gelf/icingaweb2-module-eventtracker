<?php

namespace Icinga\Module\Eventtracker\Web\Table;

use gipfl\IcingaWeb2\Link;
use gipfl\Translation\TranslationHelper;
use Icinga\Date\DateFormatter;
use Icinga\Module\Eventtracker\Engine\TimePeriod\TimeSlot;
use ipl\Html\Html;
use ipl\Html\HtmlElement;
use Ramsey\Uuid\Uuid;

class DowntimeRulesTable extends WebActionTable
{
    use TranslationHelper;

    /** @var TimeSlot[] */
    protected array $activeTimeSlots = [];

    public function setActiveTimeSlots(array $timeSlots)
    {
        $this->activeTimeSlots = $timeSlots;
    }

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
                $label = Html::tag('strong', $row->label);
                $activeSlot = $this->activeTimeSlots[Uuid::fromBytes($row->uuid)->toString()] ?? null;
                if ($activeSlot) {
                    $activeInfo = $this->describeDowntimeSlot(TimeSlot::fromSerialization($activeSlot));
                } elseif ($row->is_active === null) {
                    $activeInfo = $this->translate('Currently no downtime has been scheduled for this rule');
                } elseif ($row->is_active === 'y') {
                    $activeInfo = sprintf(
                        $this->translate('This Downtime is active since %s'),
                        $this->niceTsFormat($row->ts_triggered),
                    );
                } else {
                    $activeInfo = $this->translate('This downtime is currently not active');
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

    protected function describeDowntimeSlot(TimeSlot $slot): HtmlElement
    {
        if ($slot->end) {
            return $this->wrapActive(Html::sprintf(
                $this->translate('Currently active, slot started %s, and finishes %s'),
                Html::tag('span', ['class' => 'time-ago', 'title' => $slot->start->format('Y-m-d H:i:s')], DateFormatter::timeAgo($slot->start->getTimestamp())),
                Html::tag('span', ['class' => 'time-until', 'title' => $slot->end->format('Y-m-d H:i:s')], DateFormatter::timeUntil($slot->end->getTimestamp())),
            ));
        }

        return $this->wrapActive(Html::sprintf(
            $this->translate('Currently active, slot started %s'),
            Html::tag('span', ['class' => 'time-ago', 'title' => $slot->start->format('Y-m-d H:i:s')], DateFormatter::timeAgo($slot->start->getTimestamp()))
        ));
    }

    protected function wrapActive($content): HtmlElement
    {
        return Html::tag('span', ['style' => 'color: green'], $content);
    }

    protected function wrapInactive($content)
    {
        return Html::tag('span', ['style' => 'color: gray'], $content);
    }

    protected function niceTsFormat($ts): string
    {
        $ts = $ts / 1000;
        return $this->getDateFormatter()->getFullDay($ts) . ' ' . $this->getTimeFormatter()->getShortTime($ts);
    }

    public function prepareQuery()
    {
        return $this->db()
            ->select()
            ->from(['dr' => 'downtime_rule'], $this->getRequiredDbColumns())
            ->order('label');
    }
}
