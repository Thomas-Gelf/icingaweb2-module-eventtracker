<?php

namespace Icinga\Module\Eventtracker\Web\Widget;

use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Url;
use gipfl\Translation\TranslationHelper;
use ipl\Html\BaseHtmlElement;

class BaseEnumPropertyFilter extends BaseHtmlElement
{
    use TranslationHelper;

    protected $tag = 'div';

    protected $property;

    protected $enum;

    protected $active;

    protected $defaultAttributes = [
        'style' => 'display: inline-block'
    ];

    protected $summary;

    protected $url;

    protected $skipMissing = false;

    public function __construct($summary, Url $url)
    {
        $param = $this->property;
        $this->summary = $summary;
        $params = $url->getParams();
        if ($params->has($param)) {
            $this->active = preg_split(
                '/,/',
                $params->get($param),
                -1,
                PREG_SPLIT_NO_EMPTY
            );
        } else {
            $this->active = $this->getDefaultSelection();
        }
        $this->url = $url->without('page')->without($param);
    }

    public function skipMissing($missing = true)
    {
        $this->skipMissing = (bool) $missing;

        return $this;
    }

    protected function getDefaultSelection()
    {
        return $this->enum;
    }

    protected function assemble()
    {
        $options = \array_values($this->enum);
        $param = $this->property;
        $titleToggleOn = $this->translate('Show "%s"');
        $titleToggleOff = $this->translate('Hide "%s"');
        foreach ($options as $key) {
            $count = $this->summary->{"cnt_$key"};
            if ((int) $count === 0 && $this->skipMissing) {
                continue;
            }
            $classes = ['badge', $param, "$param-$key"];
            if ($this->active === null) {
                $isActive = true;
                $chosen = $this->enum;
            } else {
                $chosen = \array_combine($this->active, $this->active);
                $isActive = \in_array($key, $this->active);
            }
            if ($isActive) {
                unset($chosen[$key]);
                $title = $titleToggleOff;
                $classes[] = 'active';
            } else {
                $classes[] = 'disabled';
                $chosen[$key] = $key;
                $title = $titleToggleOn;
            }
            $this->add(Link::create($count > 0 ? $count : '-', $this->url->with($param, \implode(',', $chosen)), null, [
                'class' => $classes,
                'title' => \sprintf($title, $key),
            ]));
        }
    }
}
