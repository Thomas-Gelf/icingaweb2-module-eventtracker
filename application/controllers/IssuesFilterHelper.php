<?php

namespace Icinga\Module\Eventtracker\Controllers;

use gipfl\IcingaWeb2\Link;
use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;
use ipl\Html\Table;

trait IssuesFilterHelper
{
    protected $appliedFilters = [];

    protected function applyFilters(Table $table)
    {
        $table->search($this->params->get('q'));
        $main = Html::tag('ul', ['class' => 'nav']);
        $sub = Html::tag('ul');
        $main->add(Html::tag('li', null, [Link::create('Filters', '#', null, [
            'class' => 'icon-angle-double-down'
        ]), $sub]));
        $this->columnFilter($table, $sub, 'host_name', 'hosts', $this->translate('Hosts: %s'));
        $this->columnFilter($table, $sub, 'object_class', 'classes', $this->translate('Classes: %s'));
        $this->columnFilter($table, $sub, 'object_name', 'objects', $this->translate('Objects: %s'));
        $this->columnFilter($table, $sub, 'owner', 'owners', $this->translate('Owners: %s'));
        $this->columnFilter($table, $sub, 'sender_name', 'senders', $this->translate('Sender: %s'));
        if (! $this->showCompact()) {
            $this->actions()->add($main);
        }
    }

    protected function createViewToggle()
    {
        $wide = $this->params->get('wide');
        if ($wide) {
            return Link::create(
                $this->translate('Compact'),
                $this->url()->without('wide'),
                null,
                [
                    'title' => $this->translate('Switch to compact mode'),
                    'class' => 'icon-resize-small'
                ]
            );
        } else {
            return Link::create(
                $this->translate('Full'),
                $this->url()->with('wide', true),
                null,
                [
                    'title' => $this->translate('Switch to compact mode'),
                    'class' => 'icon-resize-full'
                ]
            );
        }
    }

    protected function getAppliedFilters()
    {
        return $this->appliedFilters;
    }

    protected function hasAppliedFilters(): bool
    {
        return !empty($this->appliedFilters);
    }

    protected function columnFilter(Table $table, BaseHtmlElement $parent, $column, $type, $title)
    {
        $li = Html::tag('li');
        $parent->add($li);
        $parent = $li;
        $compact = $this->showCompact();
        if ($this->params->has($column)) {
            $value = $this->params->get($column);
            $this->appliedFilters[$column] = $value;

            // TODO: move this elsewhere, here we shouldn't need to care about DB structure:
            if ($column === 'sender_name') {
                $table->joinSenders();
                $column = "s.$column";
            }
            if (strlen($value)) {
                $table->getQuery()->where("$column = ?", $value);
            } else {
                $table->getQuery()->where("$column IS NULL");
                $value = $this->translate('- none -');
            }
            if ($compact) {
                return;
            }
            $parent->add(
                Link::create(
                    sprintf($title, $value),
                    $this->url()->without($column),
                    null,
                    ['data-base-target' => '_self']
                )
            );
        } else {
            if ($compact) {
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
