<?php

namespace Icinga\Module\Eventtracker\Controllers;

use Icinga\Application\Config;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Eventtracker\Status;
use Icinga\Module\Eventtracker\Web\Table\AttributeSummaryTable;
use Icinga\Module\Eventtracker\Web\Table\BaseSummaryTable;
use Icinga\Module\Eventtracker\Web\Widget\CustomSummaryTabs;
use ipl\Html\Html;

class CustomSummariesController extends Controller
{
    protected function showAttribute(string $key)
    {
        $config = Config::module('eventtracker', 'customSummaries');
        if (! $config->hasSection($key)) {
            throw new NotFoundError('Not found');
        }
        $section = $config->getSection($key);
        $title = $section->get('label', $key);
        $attribute = $section->get('attribute', $key);
        if (! $this->showCompact()) {
            $this->tabs(new CustomSummaryTabs())->activate($key);
            $this->addTitle($this->translate('Custom Summaries by:') . ' ' . $title);
        }
        $this->addTitleWithType($title);
        $this->setAutorefreshInterval(10);
        (new AttributeSummaryTable($attribute, $title, $this->db()))->renderTo($this);
    }

    public function indexAction()
    {
        if ($key = $this->params->get('summary')) {
            $this->showAttribute($key);
            return;
        }
        if (! $this->showCompact()) {
            $this->tabs(new CustomSummaryTabs())->activate('index');
            $this->addTitle($this->translate('Custom Summaries by:'));
        }

        $db = $this->db();
        $this->setAutorefreshInterval(10);
        $main = Html::tag('div', [
            'class' => 'summary-tables'
        ]);
        $tables = [];
        foreach (Config::module('eventtracker', 'customSummaries') as $title => $section) {
            $label = $section->get('label', $title);
            $tables[$label] = new AttributeSummaryTable(
                $section->get('attribute', $title),
                $label,
                $db
            );
        }

        /** @var BaseSummaryTable $table */
        foreach ($tables as $title => $table) {
            // $this->content()->add(Html::tag('h2', $title));
            if ($this->showCompact()) {
                $table->setAttribute('data-base-target', '_next');
            }
            $table->setAttribute('data-base-target', '_next');
            $table->getQuery()->limit(10)->where('i.status = ?', Status::OPEN);
            $main->add(Html::tag('div', $table));
        }
        $this->content()->add($main);
    }

    protected function addTitleWithType($type)
    {
        $this->addTitle(sprintf(
            $this->translate('Issue Summary by %s'),
            $type
        ));
    }

    protected function showCompact()
    {
        return $this->params->get('view') === 'compact';
    }
}
