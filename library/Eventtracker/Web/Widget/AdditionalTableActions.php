<?php

namespace Icinga\Module\Eventtracker\Web\Widget;

use ipl\Html\Html;
use ipl\Html\HtmlDocument;
use gipfl\IcingaWeb2\Icon;
use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Url;
use gipfl\Translation\TranslationHelper;
use Icinga\Authentication\Auth;
use Icinga\Module\Eventtracker\Web\Table\BaseTable;

class AdditionalTableActions
{
    use TranslationHelper;

    /** @var Auth */
    protected $auth;

    /** @var Url */
    protected $url;

    /** @var BaseTable */
    protected $table;

    public function __construct(BaseTable $table, Auth $auth, Url $url)
    {
        $this->auth = $auth;
        $this->url = $url;
        $this->table = $table;
    }

    public function appendTo(HtmlDocument $parent)
    {
        $links = [];
        if (false && $this->hasPermission('eventtracker/admin')) {
            // TODO: not yet
            $links[] = $this->createDownloadJsonLink();
        }
        if ($this->hasPermission('eventtracker/showsql')) {
            $links[] = $this->createShowSqlToggle();
        }
        $parent->add($this->moreOptions($links));

        return $this;
    }

    protected function createDownloadJsonLink()
    {
        return Link::create(
            $this->translate('Download as JSON'),
            $this->url->with('format', 'json'),
            null,
            ['target' => '_blank']
        );
    }

    protected function createShowSqlToggle()
    {
        $url = $this->url;
        if ($url->getParam('format') === 'sql') {
            $link = Link::create($this->translate('Hide SQL'), $url->without('format'));
        } else {
            $link = Link::create($this->translate('Show SQL'), $this->url->with('format', 'sql'));
        }

        return $link;
    }

    protected function moreOptions($links)
    {
        $options = Html::tag('ul', ['class' => 'nav'], [
            new ToggleTableColumns($this->table, $this->url),
            Html::tag('li', null, [
                Link::create(Icon::create('down-open'), '#'),
                $this->linkList($links)
            ]),
        ]);

        return $options;
    }

    protected function linkList($links)
    {
        $ul = Html::tag('ul');

        foreach ($links as $link) {
            $ul->add(Html::tag('li', $link));
        }

        return $ul;
    }

    protected function hasPermission($permission)
    {
        return $this->auth->hasPermission($permission);
    }
}
