<?php

namespace Icinga\Module\Eventtracker\Web\Widget;

use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Url;
use gipfl\Translation\TranslationHelper;
use Icinga\Module\Eventtracker\Severity;
use ipl\Html\BaseHtmlElement;

class SummaryFilter extends BaseHtmlElement
{
    use TranslationHelper;

    protected $tag = 'div';

    protected $property = 'severity';

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
        $selection = [
            Severity::EMERGENCY,
            Severity::ALERT,
            Severity::CRITICAL,
            Severity::ERROR,
            Severity::WARNING,
        ];

        return array_combine($selection, $selection);
    }

    protected function assemble()
    {
        $options = \array_values(Severity::ENUM);
        $param = $this->property;
        $titleToggleOn = $this->translate('Show "%s"');
        $titleToggleOff = $this->translate('Hide "%s"');
        foreach ($options as $key) {
            $count = $this->summary->{"cnt_$key"};
            if ((int) $count === 0 && $this->skipMissing) {
                continue;
            }
            $classes = ['badge', "severity-$key"];
            if ($this->active === null) {
                $isActive = true;
                $chosen = Severity::ENUM;
            } else {
                $chosen = \array_combine($this->active, $this->active);
                $isActive = \in_array($key, $this->active);
            }
            if ($isActive) {
                unset($chosen[$key]);
                $title = $titleToggleOff;
                $classes[] = 'active';
            } else {
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
