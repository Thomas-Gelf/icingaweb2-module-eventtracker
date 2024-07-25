<?php

namespace Icinga\Module\Eventtracker\Web\Table;

use gipfl\Translation\TranslationHelper;
use Icinga\Module\Eventtracker\Engine\Downtime\DowntimeRule;

class DowntimeScheduleTable extends BaseTable
{
    use TranslationHelper;

    protected $db;

    /** @var DowntimeRule */
    protected $rule;

    /** @var ?string */
    protected $currentDateString = null;

    public function __construct($db, DowntimeRule $rule)
    {
        parent::__construct($db);
        $this->db = $db;
        $this->rule = $rule;
    }

    protected function initialize()
    {
        $this->addAvailableColumns([
            $this->createColumn('ts_expected_start', $this->translate('Expected Start'), 'ts_expected_start')
                ->setRenderer(function ($row) {
                    return $this->niceTsFormat($row->ts_expected_start);
                }),
            $this->createColumn('ts_expected_end', $this->translate('Expected End'), 'ts_expected_end')
                ->setRenderer(function ($row) {
                    return $this->niceTsFormat($row->ts_expected_end);
                }),
            $this->createColumn('ts_started', $this->translate('Started'), 'ts_started')
                ->setRenderer(function ($row) {
                    return $this->niceTsFormat($row->ts_started);
                }),
        ]);
    }

    protected function niceTsFormat(?int $ts): string
    {
        if ($ts === null) {
            return '-';
        }
        $ts = $ts / 1000;
        // return date('Y-m-d H:i', $ts);
        $date = $this->getDateFormatter()->getFullDay($ts);
        $time = $this->getTimeFormatter()->getShortTime($ts);
        if ($date === $this->currentDateString) {
            return $time;
        }
        $this->currentDateString = $date;

        return  "$date $time";
    }

    public function prepareQuery()
    {
        return $this->db()
            ->select()
            ->from(['dc' => 'downtime_calculated'], $this->getRequiredDbColumns())
            ->where('dc.rule_config_uuid = ?', $this->rule->get('config_uuid'))
            ->order('ts_expected_start');
    }
}
