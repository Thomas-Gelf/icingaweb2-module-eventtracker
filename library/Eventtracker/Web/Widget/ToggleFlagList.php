<?php

namespace Icinga\Module\Eventtracker\Web\Widget;

use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Url;
use gipfl\Translation\TranslationHelper;
use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;

abstract class ToggleFlagList extends BaseHtmlElement
{
    use TranslationHelper;

    protected $tag = 'li';

    /** @var Url */
    private $url;

    /** @var string */
    private $param;

    public function __construct(Url $url, $param)
    {
        $this->url = $url;
        $this->param = $param;
    }

    abstract protected function getListLabel();

    abstract protected function getOptions();

    protected function getDefaultSelection()
    {
        return \array_keys($this->getOptions());
    }

    protected function setEnabled($enabled)
    {
        // You might want to override this method
    }

    protected function assemble()
    {
        $link = Link::create($this->getListLabel(), '#', null, ['class' => 'icon-angle-double-down', 'noclass' => 'icon-th-list']);
        $this->add([
            $link,
            $this->createLinkList($this->toggleColumnsOptions($link))
        ]);


    }

    protected function toggleColumnsOptions(Link $mainLink)
    {
        $default = $this->getDefaultSelection();
        $links = [];
        $url = $this->url;
        $param = $this->param;

        $enabled = $url->getParam($param);
        if ($enabled === null) {
            $enabled = $default;
        } else {
            $mainLink->getAttributes()->set(
                'class', 'icon-filter'
            );
            $links[] = $this->geturlReset();
            $enabled = $this->splitUrlOptions($enabled);
            $this->setEnabled($enabled);
        }

        $all = [];
        $disabled = [];
        foreach ($this->getOptions() as $option => $label) {
            $all[] = $option;
            if (\in_array($option, $enabled)) {
                $urlOptions = \array_diff($enabled, [$option]);
                $icon = 'check';
                $title = sprintf($this->translate('Hide %s'), $label);
            } else {
                $disabled[] = $option;
                $urlOptions = \array_merge($enabled, [$option]);
                $icon = 'plus';
                $title = sprintf($this->translate('Show %s'), $label);
            }
            $links[] = Link::create($label, $this->getUrlWithOptions($urlOptions), null, [
                'class' => "icon-$icon",
                'title' => $title,
            ]);
        }
        if (! empty($disabled) && $all !== $default) {
            \array_unshift($links, Link::create(
                $this->translate('All'),
                $url->with($param, $this->joinUrlOptions($all)),
                null,
                [
                    'class' => 'icon-resize-horizontal',
                    'data-base-target' => '_main'
                ]
            ));
        }

        return $links;
    }

    protected function geturlReset()
    {
        return Link::create(
            $this->translate('Reset'),
            $this->url->without($this->param),
            null,
            ['class' => 'icon-reply']
        );
    }

    protected function getUrlWithOptions($options)
    {
        return $this->url->with($this->param, $this->joinUrlOptions($options));
    }

    protected function joinUrlOptions($value)
    {
        return \implode(',', $value);
    }

    protected function splitUrlOptions($value)
    {
        return \preg_split('/,/', $value, -1, PREG_SPLIT_NO_EMPTY);
    }

    protected function createLinkList($links)
    {
        $ul = Html::tag('ul');

        foreach ($links as $link) {
            $ul->add(Html::tag('li', $link));
        }

        return $ul;
    }
}
