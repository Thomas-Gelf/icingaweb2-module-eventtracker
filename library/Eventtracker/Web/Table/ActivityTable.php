<?php

namespace Icinga\Module\Eventtracker\Web\Table;

use Icinga\Module\Eventtracker\Issue;
use Icinga\Module\Eventtracker\Time;
use Icinga\Module\Eventtracker\Web\Widget\ConfigDiff;
use ipl\Html\Html;

class ActivityTable extends BaseTable
{
    protected $defaultAttributes = [
        'common-table'
    ];
    protected $issue;

    public function __construct($db, Issue $issue)
    {
        parent::__construct($db);
        $this->issue = $issue;
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
            if ($property === 'attributes') {
                $oldA = '';
                $newA = '';
                foreach (\json_decode($old) as $name => $value) {
                    $oldA .= "$name = $value\n";
                }
                foreach (\json_decode($new) as $name => $value) {
                    $newA .= "$name = $value\n";
                }
                $old = trim($oldA);
                $new = trim($newA);
                $result->add(ConfigDiff::create($old, $new));
            } else {
                $result->add("$old -> $new");
            }
        }

        return $result;
    }

    public function prepareQuery()
    {
        return $this->db()->select()
            ->from(['i' => 'issue_activity'], $this->getRequiredDbColumns())
            ->where('issue_uuid = ?', $this->issue->getUuid())
            ->order('ts_modified DESC');
    }
}
