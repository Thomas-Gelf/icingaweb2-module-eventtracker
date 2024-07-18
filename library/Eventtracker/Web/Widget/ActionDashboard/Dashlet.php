<?php

namespace Icinga\Module\Eventtracker\Web\Widget\ActionDashboard;

use gipfl\IcingaWeb2\Icon;
use gipfl\IcingaWeb2\Link;
use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;

class Dashlet extends BaseHtmlElement
{
    protected $tag = 'li';

    protected $icon = 'help';

    protected $title;

    protected $url;

    protected $summary;

    protected $classes;

    public function __construct($title, $url, $icon, $summary, $classes = null)
    {
        $this->title = $title;
        $this->url = $url;
        $this->icon = $icon;
        $this->summary = $summary;
        $this->classes = $classes;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function getUrl()
    {
        return $this->url;
    }

    public function getSummary()
    {
        return $this->summary;
    }

    protected function getIconName()
    {
        return $this->icon;
    }

    public function listCssClasses()
    {
        return $this->classes;
    }

    protected function assemble()
    {
        $this->add(Link::create([
            $this->getTitle(),
            Icon::create($this->getIconName()),
            Html::tag('p', null, $this->getSummary())
        ], $this->getUrl(), null, [
            'class' => $this->listCssClasses()
        ]));

        return parent::renderContent();
    }
}
