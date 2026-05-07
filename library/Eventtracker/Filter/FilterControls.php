<?php

namespace Icinga\Module\Eventtracker\Filter;

use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Url;
use gipfl\IcingaWeb2\Widget\ActionBar;
use gipfl\Translation\TranslationHelper;
use Icinga\Web\UrlParams;
use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;

class FilterControls
{
    use TranslationHelper;
    private Url $url;
    private bool $isCompact;
    private ActionBar $actionBar;
    private UrlParams $params;
    protected array $appliedFilters = [];
    public function __construct(Url $url, bool $isCompact, ActionBar $actionBar)
    {
        $this->url = $url;
        $this->isCompact = $isCompact;
        $this->actionBar = $actionBar;
        $this->params = $this->url->getParams();
    }

    public function hasAppliedFilters(): bool
    {
        return !empty($this->appliedFilters);
    }

    public function prepareFilterControls()
    {
        $isCompact = $this->isCompact;
        $main = Html::tag('ul', ['class' => 'nav']);
        $sub = Html::tag('ul');
        $main->add(Html::tag('li', null, [Link::create('Filters', '#', null, [
            'class' => 'icon-angle-double-down'
        ]), $sub]));

        $this->addFilterControl($sub, 'host_name', 'hosts', $this->translate('Hosts %s'), $isCompact);
        $this->addFilterControl($sub, 'object_class', 'classes', $this->translate('Classes: %s'), $isCompact);
        $this->addFilterControl($sub, 'object_name', 'objects', $this->translate('Objects: %s'), $isCompact);
        $this->addFilterControl($sub, 'owner', 'owners', $this->translate('Owners: %s'), $isCompact);
        $this->addFilterControl($sub, 'label', 'inputs', $this->translate('Input: %s'), $isCompact);
        $this->addFilterControl($sub, 'sender_name', 'senders', $this->translate('Sender: %s'), $isCompact);

        if (! $this->isCompact) {
            $this->actionBar->add($main);
        }
    }

    protected function addFilterControl(BaseHtmlElement $parent, $column, $type, $title, $isCompact)
    {
        $li = Html::tag('li');
        $parent->add($li);
        $parent = $li;
        if ($this->params->has($column)) {
            $value = $this->params->get($column);
            $this->appliedFilters[$column] = $value;
            if ($isCompact) {
                return;
            }
            $parent->add(
                Link::create(
                    sprintf($title, $value),
                    $this->url->without($column),
                    null,
                    ['data-base-target' => '_self']
                )
            );
        } else {
            if ($isCompact) {
                return;
            }
            $parent->add(
                Link::create(
                    sprintf($title, $this->translate('all')),
                    "eventtracker/summary/$type",
                    null,
                    ['data-base-target' => '_next']
                )
            );
        }
    }
}
