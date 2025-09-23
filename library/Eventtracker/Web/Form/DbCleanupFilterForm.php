<?php

namespace Icinga\Module\Eventtracker\Web\Form;

use gipfl\Translation\TranslationHelper;
use gipfl\Web\Form;
use Icinga\Module\Eventtracker\Db\DbCleanupFilter;
use Icinga\Module\Eventtracker\Severity;
use ipl\Html\FormElement\SubmitElement;
use RuntimeException;

class DbCleanupFilterForm extends Form
{
    use TranslationHelper;

    protected bool $runSimulation;
    protected DbCleanupFilter $filter;

    protected function assemble()
    {
        $this->addElement('select', 'table', [
            'label' => $this->translate('DB Table'),
            'description' => $this->translate('Whether to clean up the current issues table, or the history table'),
            'options' => [
                null      => $this->translate('Please choose'),
                'issues'  => $this->translate('Issues'),
                'history' => $this->translate('History'),
            ],
            'required' => true,
        ]);
        $this->addElement('number', 'keep-days', [
            'label' => $this->translate('Days to keep'),
            'description' => $this->translate('Keep the given amount of days, delete everything older'),
        ]);
        $this->addElement('text', 'host_name', [
            'label' => $this->translate('Hostname'),
            'description' => $this->translate(
                'Deletes only issues for the given host. Wildcards (*) are allowed, comma-separated multiple values'
                . ' will be combined using binary OR logic'
            ),
        ]);
        $this->addElement('text', 'object_class', [
            'label' => $this->translate('Object Class'),
            'description' => $this->translate('Deletes only issues for the given object class(es)'),
        ]);
        $this->addElement('text', 'object_name', [
            'label' => $this->translate('Object name'),
            'description' => $this->translate('Deletes only issues for the given object name(s)'),
        ]);
        $this->addElement('select', 'keep-severity', [
            'label' => $this->translate('Keep Severity'),
            'description' => $this->translate(
                'Do not delete issues with a severity equal or greater than the selected one'
            ),
            'options' => [
                null                    => $this->translate('Please choose'),
                Severity::EMERGENCY     => Severity::EMERGENCY,
                Severity::ALERT         => Severity::ALERT,
                Severity::CRITICAL      => Severity::CRITICAL,
                Severity::ERROR         => Severity::ERROR,
                Severity::WARNING       => Severity::WARNING,
                Severity::NOTICE        => Severity::NOTICE,
                Severity::INFORMATIONAL => Severity::INFORMATIONAL,
                Severity::DEBUG         => Severity::DEBUG,
            ],
        ]);
        $simulateLabel = $this->translate('Simulate');
        if ($this->hasBeenSent() && (int) $this->getSentValue('keep-days') < 1) {
            if ($this->getSentValue('simulate') !== $simulateLabel) {
                $this->addElement('boolean', 'force', [
                    'label'       => $this->translate('Force'),
                    'description' => $this->translate('No time restriction has been given, delete anyway'),
                    'required'    => true,
                ]);
            }
        }
        $this->addElement('submit', 'simulate', [
            'label' => $simulateLabel
        ]);
        $this->addElement('submit', 'delete', [
            'label' => $this->translate('Delete')
        ]);
    }

    public function wantsSimulation(): bool
    {
        return $this->runSimulation;
    }

    public function getFilter(): DbCleanupFilter
    {
        return $this->filter;
    }

    public function getTable(): string
    {
        return $this->getValue('table');
    }

    protected function onSuccess()
    {
        /** @var SubmitElement $simulate */
        $simulate = $this->getElement('simulate');
        /** @var SubmitElement $delete */
        $delete = $this->getElement('delete');
        if ($simulate->hasBeenPressed()) {
            $this->runSimulation = true;
        } elseif ($delete->hasBeenPressed()) {
            $this->runSimulation = false;
        } else {
            throw new RuntimeException('No known button has been pressed');
        }
        $before = time() - 86_400 * (int) $this->getValue('keep-days');
        $filters = [];
        foreach (['host_name', 'object_class', 'object_name'] as $key) {
            if ($filter = $this->getValue($key)) {
                $filters[$key] = preg_split('/\s*,\s*/', $filter, -1, PREG_SPLIT_NO_EMPTY); // Multiple ones are allowed
            }
        }
        $this->filter = new DbCleanupFilter($before, $filters, $this->getValue('keep-severity'));
    }
}
