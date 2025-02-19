<?php

namespace Icinga\Module\Eventtracker\Web\Widget;

use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Url;
use gipfl\Translation\TranslationHelper;
use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;

class SeverityBadges extends BaseHtmlElement
{
    use TranslationHelper;

    protected $tag = 'div';

    protected $property = 'severity';

    protected $defaultAttributes = [
        'style' => 'display: inline-block; white-space: nowrap; '
    ];

    protected $summary;

    protected $url;

    public function __construct($summary, Url $url)
    {
        $this->summary = $summary;
        $this->url = $url->without('page');
    }

    protected function assemble()
    {
        $param = $this->property;
        $title = $this->translate('Show "%s"');

        foreach ((array) $this->summary as $key => $value) {
            if (substr($key, 0, 4) !== 'cnt_' || substr($key, -7) === 'handled') {
                continue;
            }
            $key = substr($key, 4);
            $count = (int) $value;
            if ($count === 0) {
                continue;
            }
            $countHandled = (int) $this->summary->{"cnt_{$key}_handled"};
            $countUnhandled = (int) $this->summary->{"cnt_{$key}_unhandled"};
            $classes = ['badge', 'active', $param, "$param-$key"];

            if ($countUnhandled === 0) {
                $classes[] = 'handled';
            }
            $link = Link::create(
                $countUnhandled > 0 ? $countUnhandled : '',
                $this->url->with($param, $key), // ->with('status', 'open')?
                null,
                [
                    'class' => $classes,
                    'title' => \sprintf($title, $key),
                ]
            );

            if ($countHandled > 0) {
                $link->add(Html::tag('span', ['class' => 'handled'], "+$countHandled"));
            }
            $this->add($link);
        }
    }
}
