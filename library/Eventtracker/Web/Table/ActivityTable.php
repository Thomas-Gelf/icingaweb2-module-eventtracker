<?php

namespace Icinga\Module\Eventtracker\Web\Table;

use gipfl\Diff\HtmlRenderer\SideBySideDiff;
use gipfl\Diff\PhpDiff;
use Icinga\Module\Eventtracker\Issue;
use Icinga\Module\Eventtracker\Time;
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

    protected function showModifications($modifications): array
    {
        $result = [];
        foreach ($modifications as $property => $modification) {
            if (is_array($modification)) {
                list($old, $new) = $modification;
                if (substr($property, 0, 3) === 'ts_') {
                    if ($old !== null) {
                        $old = self::formatMsTime($old);
                    }
                    if ($new !== null) {
                        $new = self::formatMsTime($new);
                    }
                }

                $result[] = $this->showModification($property, $old, $new);
            } else {
                if (substr($property, 0, 3) === 'ts_') {
                    if ($modification !== null) {
                        $modification = self::formatMsTime($modification);
                    }
                }
                $result[] = $this->showModification($property, null, $modification);
            }
        }

        return $result;
    }

    protected static function formatMsTime(int $ms)
    {
        return  substr(Time::timestampMsToDateTime($ms)->format("Y-m-d H:i:s.u"), 0, -3);
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
                $result->add(new SideBySideDiff(new PhpDiff($old, $new)));
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
