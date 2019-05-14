<?php

namespace Icinga\Module\Eventtracker\Web\Widget;

use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Url;
use gipfl\Translation\TranslationHelper;
use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;

class Dashlet extends BaseHtmlElement
{
    use TranslationHelper;

    protected $tag = 'div';

    protected $target = 'col1';

    /** @var string */
    protected $title;

    /** @var Url */
    protected $url;

    protected $defaultAttributes = [
        'class' => 'container',
    ];

    public function __construct($url, $title)
    {
        if ($url instanceof Url) {
            $this->url = $url;
        } else {
            $this->url = Url::fromPath($url);
        }
        $this->title = $title;
    }

    protected function assemble()
    {
        $url = $this->url;

        $this->getAttributes()->add('data-icinga-url', $url->with('view', 'compact')->getAbsoluteUrl());
        $tooltip = sprintf($this->translate('Show %s'), $this->title);

        $this->add(
            Html::tag('h1', Link::create(
                $this->title,
                $url->without(['view', 'limit']),
                null,
                [
                    'title' => $tooltip,
                    'aria-label' => $tooltip,
                    'data-base-target' => $this->target,
                ]
            ))
        );
        $this->add(Html::tag('p', [
            'class' => 'progress-label',
        ], $this->translate('Loading'))->add([
            Html::tag('span', '.'),
            Html::tag('span', '.'),
            Html::tag('span', '.'),
        ]));
        $this->add(Html::tag('noscript', Html::tag('iframe', [
            'src' => $url->with('isIframe', true),
            'style' => 'height:100%; width:99%',
            'frameborder' => 'no',
            // 'title' => '{TITLE_PREFIX}{TITLE}',
        ])));
    }

    public function setTarget($target)
    {
        $this->target = $target;

        return $this;
    }
}
