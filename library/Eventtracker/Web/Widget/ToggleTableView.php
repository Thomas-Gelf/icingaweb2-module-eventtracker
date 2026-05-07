<?php

namespace Icinga\Module\Eventtracker\Web\Widget;

use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Url;
use gipfl\Translation\TranslationHelper;
use Icinga\Web\UrlParams;
use ipl\Html\BaseHtmlElement;

class ToggleTableView extends BaseHtmlElement
{
    use TranslationHelper;

    protected $tag = 'ul';

    protected $defaultAttributes = ['class' => 'nav'];
    private Url $url;
    private UrlParams $params;
    public function __construct(Url $url)
    {
        $this->url = $url;
        $this->params = $this->url->getParams();
    }

    public function assemble()
    {
        $this->add($this->createViewToggle());
    }

    protected function createViewToggle(): Link
    {
        $wide = $this->params->get('wide');
        if ($wide) {
            return Link::create(
                $this->translate('Compact'),
                $this->url->without('wide'),
                null,
                [
                    'title' => $this->translate('Switch to compact mode'),
                    'class' => 'icon-resize-small'
                ]
            );
        } else {
            return Link::create(
                $this->translate('Full'),
                $this->url->with('wide', true),
                null,
                [
                    'title' => $this->translate('Switch to compact mode'),
                    'class' => 'icon-resize-full'
                ]
            );
        }
    }
}
