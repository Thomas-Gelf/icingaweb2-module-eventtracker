<?php

namespace Icinga\Module\Eventtracker\Web\Widget;

use gipfl\Translation\TranslationHelper;
use gipfl\ZfDb\Adapter\Adapter as Db;
use Icinga\Module\Eventtracker\Issue;
use Icinga\Module\Eventtracker\Web\Table\ActivityTable;
use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;

class IssueActivities extends BaseHtmlElement
{
    use TranslationHelper;

    protected $tag = 'div';

    protected $defaultAttributes = [
    ];

    /** @var Issue */
    protected $issue;

    /** @var Db */
    protected $db;

    public function __construct(Issue $issue, Db $db)
    {
        $this->issue = $issue;
        $this->db = $db;
    }

    protected function assemble()
    {
        $activities = new ActivityTable($this->db, $this->issue);
        if ($activities->count()) {
            $this->add(Html::tag('div', [
                'class' => 'output comment'
            ], [
                Html::tag('h2', 'CHANGES'),
                Html::tag('div', ['class' => 'activities'], $activities)
            ]));
        }
    }
}
