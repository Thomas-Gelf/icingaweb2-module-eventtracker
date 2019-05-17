<?php

namespace Icinga\Module\Eventtracker\Web\Table;

use Icinga\Module\Eventtracker\Incident;
use Icinga\Module\Eventtracker\Time;
use ipl\Html\Html;

class ActivityTable extends BaseTable
{
    protected $defaultAttributes = [
        'common-table'
    ];
    protected $incident;

    public function __construct($db, Incident $incident)
    {
        parent::__construct($db);
        $this->incident = $incident;
    }

    protected function renderTitleColumns()
    {
        return null;
    }

    protected function initialize()
    {
        $this->addAvailableColumns([
            $this->createColumn('ts_modified', $this->translate('Time'))->setRenderer(function ($row) {
                return Html::tag('td', [
                    'style' => 'width: 8em'
                ], Time::agoFormatted($row->ts_modified));
            }),
            $this->createColumn('modifications', $this->translate('Changes'))->setRenderer(function ($row) {
                return $this->showModifications(json_decode($row->modifications));
            }),
        ]);
    }

    protected function showModifications($modifications)
    {
        foreach ($modifications as $property => list($old, $new)) {
            $result[] = $this->showModification($property, $old, $new);
        }

        return $result;
    }

    protected function showModification($property, $old, $new)
    {
        $result = Html::tag('pre', Html::tag('strong', "$property: "));
        if ($old === null) {
            $result->add($new);
        } elseif ($new === null) {
            $result->add([Html::tag('del', $old), ' (removed)']);
        } else {
            $result->add("$old -> $new");
        }

        return $result;
    }

    public function prepareQuery()
    {
        return $this->db()->select()
            ->from(['i' => 'incident_activity'], $this->getRequiredDbColumns())
            ->where('incident_uuid = ?', $this->incident->getUuid())
            ->order('ts_modified DESC');
    }
}
