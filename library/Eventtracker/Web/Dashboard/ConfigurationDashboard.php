<?php

namespace Icinga\Module\Eventtracker\Web\Dashboard;

use gipfl\Translation\TranslationHelper;
use Icinga\Module\Eventtracker\Web\WebActions;
use Icinga\Module\Eventtracker\Web\Widget\ActionDashboard\Dashlet;
use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;

class ConfigurationDashboard extends BaseHtmlElement
{
    use TranslationHelper;

    protected $tag = 'div';

    /** @var WebActions */
    protected $webActions;

    public function __construct(WebActions $webActions)
    {
        $this->webActions = $webActions;
    }

    /**
     * @throws \Icinga\Exception\NotFoundError
     */
    protected function assemble(): void
    {
        $this->addAttributes([
            'class' => 'action-dashboard',
            'data-base-target' =>'_main'
        ]);

        foreach ($this->webActions->getGroups() as $groupLabel => $actions) {
            $this->add(Html::tag('h1', $groupLabel));
            $this->add($ul = Html::tag('ul', [
                'class' => 'gipfl-dashboard-actions',
                // 'data-base-target' => '_next',
            ]));

            foreach ($actions as $action) {
                $ul->add(new Dashlet(
                    $action->plural,
                    $action->listUrl,
                    $action->icon,
                    $action->description
                ));
            }
        }
    }
}
