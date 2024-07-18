<?php

namespace Icinga\Module\Central\Web\Widget;

use ipl\Html\Html;
use ipl\Html\HtmlDocument;

class Dashboard extends HtmlDocument
{
    protected $title;

    /** @var Dashlet[] */
    protected $dashlets = [];

    /**
     * Dashboard constructor.
     * @param $title
     * @param Dashlet[] $dashlets
     */
    public function __construct($title, $dashlets = [])
    {
        $this->title = $title;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function addDashlet(Dashlet $dashlet)
    {
        $this->dashlets[] = $dashlet;

        return $this;
    }

    protected function assemble()
    {
        $this->add(Html::tag('h1', $this->getTitle()));
        $this->add(Html::tag('ul', [
            'class'            => 'main-actions',
            'data-base-target' => '_next',
        ], $this->dashlets));
    }
}
