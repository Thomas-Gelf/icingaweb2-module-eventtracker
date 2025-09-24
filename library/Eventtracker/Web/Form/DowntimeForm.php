<?php

namespace Icinga\Module\Eventtracker\Web\Form;

use Cron\CronExpression;
use gipfl\Format\LocalDateFormat;
use gipfl\Format\LocalTimeFormat;
use gipfl\Translation\TranslationHelper;
use gipfl\Web\Form\Decorator\DdDtDecorator;
use gipfl\Web\Form\Element\TextWithActionButton;
use gipfl\Web\Widget\Hint;
use gipfl\ZfDbStore\DbStorableInterface;
use gipfl\ZfDbStore\ZfDbStore;
use Icinga\Authentication\Auth;
use Icinga\Data\Filter\Filter;
use Icinga\Data\Filter\FilterAnd;
use Icinga\Data\Filter\FilterMatch;
use Icinga\Module\Eventtracker\ConfigHistory;
use Icinga\Module\Eventtracker\Engine\Downtime\DowntimeRule;
use Icinga\Module\Eventtracker\Time;
use Icinga\Module\Eventtracker\Web\Form\Validator\CronExpressionValidator;
use Icinga\Web\Notification;
use ipl\Html\Html;
use Ramsey\Uuid\Uuid;

class DowntimeForm extends UuidObjectForm
{
    use TranslationHelper;

    protected const FILTER_PREFIX = 'f_';

    protected string $table = 'downtime_rule';
    protected array $multiSelectElements = [
        'day_of_week',
        'month',
    ];

    protected array $tsCombinations = [
        'ts_not_before',
        'ts_not_after',
    ];

    protected array $timeProperties = [
        'duration',
        'max_single_problem_duration',
    ];

    protected ?DbStorableInterface $object = null;
    protected ?array $allowedSimpleFilterProperties = null;
    private bool $complexFilterIsNotSimple = false;

    protected function assemble()
    {
        $this->addMainProperties();

        $this->addHtml(Html::tag('h3', $this->translate('Filter settings')));
        $this->addFilterElements();

        $this->addHtml(Html::tag('h3', $this->translate('Time constraints')));
        $this->addTimeConstraints();

        $this->addHtml(Html::tag('h3', $this->translate('Issue handling')));
        $this->addIssueHandlingSettings();

        $this->addButtons();
    }

    public function addMainProperties(): void
    {
        $this->addElement('text', 'label', [
            'label' => $this->translate('Label'),
            'class' => 'autofocus',
            'required' => true,
        ]);
        $this->addElement('textarea', 'message', [
            'label' => $this->translate('Rule Description / Message'),
            'required' => true,
        ]);
        $this->addElement('select', 'is_enabled', [
            'label' => $this->translate('Enabled'),
            'description' => $this->translate(
                'Disabling a Rule has immediate effect on all related calculated Downtimes'
            ),
            'options' => [
                'y' => $this->translate('Yes'),
                'n' => $this->translate('No'),
            ],
        ]);
    }

    /**
     * Returns an array with (prefixed) element name => filter pairs, in case the
     * given filter qualifies as "simple", null otherwise
     */
    protected function getSimpleFilterFormValues(Filter $filter): ?array
    {
        if ($filter->isExpression()) {
            $filter = Filter::matchAll([$filter]);
        }
        if (!$filter instanceof FilterAnd) {
            return null;
        }
        $filterArray = [];
        $simpleFilterProperties = $this->getAllowedSimpleFilterProperties();
        foreach ($filter->filters() as $subFilter) {
            if ($subFilter instanceof FilterMatch && isset($simpleFilterProperties[$subFilter->getColumn()])) {
                $filterArray[self::FILTER_PREFIX . $subFilter->getColumn()] = $subFilter->getExpression();
            } else {
                return null;
            }
        }
        if (empty($filterArray)) {
            return null;
        }

        return $filterArray;
    }

    public function addFilterElements(): void
    {
        $this->addElement('select', 'filter_type', [
            'label'    => $this->translate('Filter Type'),
            'ignore'   => true,
            'required' => true,
            'class'    => 'autosubmit',
            'options' => [
                null            => $this->translate('- please choose -'),
                'simple_filter' => $this->translate('Apply to all Events with specific properties'),
                'custom_filter' => $this->translate('Define a custom filter'),
                'host_list'     => $this->translate('Apply hosts in a given list'),
            ],
        ]);

        if ($this->getValue('filter_type') === 'simple_filter') {
            foreach ($this->getAllowedSimpleFilterProperties() as $propertyName => $label) {
                $this->addElement('text', self::FILTER_PREFIX . $propertyName, [
                    'label' => $label,
                    'ignore' => true
                ]);
            }
        } elseif ($this->getValue('filter_type') === 'custom_filter') {
            if ($this->hasBeenSent()
                && $this->getSentValue('filter_type') === 'simple_filter'
                && $this->complexFilterIsNotSimple
            ) {
                $errorPrefix = Html::tag('span', [
                    'class' => 'error'
                ], [Html::tag('strong', $this->translate('Error')), ': ', $this->translate(
                    'This filter cannot be converted to a simple filter, please clear the field or remove'
                    . ' unsupported properties, before switching to a simple filter.'
                ), Html::tag('br')]);
            } else {
                $errorPrefix = '';
            }
            $this->addElement('text', 'filter_definition', [
                'label' => $this->translate('Custom filter'),
                'required'    => true,
                'description' => Html::sprintf(
                    '%s' . $this->translate(<<<'EOT'
Filter to restrict where this Rule should be applied.
A filter consists of filter expressions in the format %s.
%s can be any issue property,
and you can use the asterisk %s as a wildcard match placeholder in %s.
Issue attributes can be accessed via %s and custom variables via %s.
Expressions can be combined via %s (and) and %s (or),
and you can also use parentheses to group expressions.'
EOT
                    ),
                    $errorPrefix,
                    Html::tag('b', 'key=value'),
                    Html::tag('b', 'key'),
                    Html::tag('b', '*'),
                    Html::tag('b', 'value'),
                    Html::tag('b', 'attributes.key'),
                    Html::tag('b', 'host.vars.os'),
                    Html::tag('b', '&'),
                    Html::tag('b', '|')
                ),
                'placeholder' => 'severity=critical&message=*SAP*&attributes.env=production&host.vars.os=Linux'
            ]);
            $this->addHidden('host_list_uuid');
        } elseif ($this->getValue('filter_type') === 'host_list') {
            $this->addElement('select', 'host_list_uuid', [
                'label' => $this->translate('Host list'),
                'required' => true,
                'description' => $this->translate(
                    'Apply this Downtime Rule to all Hosts in a fixed (or dynamic) host list'
                ),
                'options' => [null => $this->translate('- please choose (optional) -')] + $this->enumHostLists(),
            ]);
            $this->addHidden('filter_definition');
        }
    }

    public function addTimeConstraints(): void
    {
        $this->addElement('select', 'recurrence_type', [
            'label' => $this->translate('Recurrence'),
            'ignore' => true,
            'required' => true,
            'class' => 'autosubmit',
            'options' => [
                null       => $this->translate('- please choose -'),
                'run_once' => $this->translate('run once'),
                '@hourly'  => $this->translate('hourly'),
                '@daily'   => $this->translate('daily'),
                '@weekly'  => $this->translate('weekly'),
                '@monthly' => $this->translate('monthly'),
                '@yearly'  => $this->translate('yearly'),
                'custom'   => $this->translate('based on custom rules'),
                'cron'     => $this->translate('write a cron expression'),
            ],
        ]);
        // time_definition
        switch ($this->getValue('recurrence_type')) {
            case 'run_once':
                $this->addDurationElements();
//                $this->removeTsCombination('ts_not_after');
                break;
            case 'custom':
                $this->addDurationElements();
                $this->addCustomTimeDefinitionElements();
                break;
            case 'cron':
                $this->addDurationElements();
                $this->addCronExpression();
                break;
            default: // daily, weekly...
                if ($this->getValue('recurrence_type') !== null) {
                    $this->addDurationElements();
                }
        }
    }

    protected function addIssueHandlingSettings(): void
    {
        $subject = $this->runsOnce()
            ? $this->translate('this Downtime')
            : $this->translate('every iteration of this Downtime');
        $this->addElement('time', 'max_single_problem_duration', [
            'label'       => $this->translate('Problem duration limit'),
            'description' => sprintf($this->translate(
                'When configured, this allows for every affected (uniquely identified)'
                . ' problem to occur only once during %s (value is hours:minutes)'
            ), $subject),
        ]);
        $subject = $this->runsOnce()
            ? $this->translate('this Downtime')
            : $this->translate('an iteration of this Downtime');
        $this->addElement('select', 'on_iteration_end_issue_status', [
            'label' => $this->translate('When finished'),
            'options' => [
                null => $this->translate('- please choose -'),
                'open'  => $this->translate('The issue should be re-opened'),
                'closed' => $this->translate('The issue should be closed'),
            ],
            'description' => sprintf(
                $this->translate(
                    'What should happen with affected issues, once %s ends,'
                    . ' or when the problem duration limit has been exceeded'
                ),
                $subject
            )
        ]);
    }

    protected function runsOnce(): bool
    {
        return $this->getValue('recurrence_type') ===  'run_once';
    }

    protected function addDurationElements()
    {
        $this->addElement('text', 'timezone', [
            'label' => $this->translate('Timezone'),
            'description' => $this->translate(
                'While the UI continues to show time information in your current/chosen time zone,'
                . ' Downtime calculation will take place based on the configured time zone'
            ),
            'value' => 'Europe/Berlin',
        ]);
        $this->addTsCombination('ts_not_before', [
            'label'       => $this->runsOnce() ? $this->translate('Start time') : $this->translate('Activate after'),
            'description' => $this->runsOnce()
                ? $this->translate('This downtime will be triggered at the specified time')
                : $this->translate('The first calculated iteration will take place after this time'),
            'value'       => $this->object ? null : Time::unixMilli(),
        ]);
        $this->addTsCombination('ts_not_after', [
            'label' => $this->translate('Stop time'),
            'description' => $this->translate('If specified, this downtime stops at the given time')
        ]);
        $subject = $this->runsOnce()
            ? $this->translate('this Downtime')
            : $this->translate('every iteration of this Downtime');
        $this->addElement('time', 'duration', [
            'label' => $this->translate('Duration'),
            'description' => sprintf($this->translate('How long should %s last? (value is hours:minutes)'), $subject),
        ]);
    }

    protected function addCustomTimeDefinitionElements()
    {
        $this->add(Hint::info(Html::sprintf(
            $this->translate('Your Rules will be evaluated relative to the start time (%s) defined above'),
            Html::tag('strong', $this->getElement('ts_not_after_date')->getLabel())
        )));
        $this->addElement('number', 'cron_hour', [
            'label' => $this->translate('Start at a specific hour'),
            'ignore' => true,
        ]);
        $this->addElement('number', 'cron_hour', [
            'label' => $this->translate('Start at a specific minute'),
            'ignore' => true,
        ]);
        $this->addElement('select', 'day_of_week', [
            'label' => $this->translate('Only specific weekdays'),
            'ignore' => true,
            'multiple' => true,
            'options' => [
                '1' => $this->translate('Monday'),
                '2' => $this->translate('Tuesday'),
                '3' => $this->translate('Wednesday'),
                '4' => $this->translate('Thursday'),
                '5' => $this->translate('Friday'),
                '6' => $this->translate('Saturday'),
                '0' => $this->translate('Sunday'),
            ],
            'description' => $this->translate('Runs on every weekday, if not specified'),
        ]);
        $this->addElement('select', 'month', [
            'label' => $this->translate('Only specific months'),
            'ignore'   => true,
            'multiple' => true,
            'options' => [
                '1' => $this->translate('January'),
                '2' => $this->translate('February'),
                '3' => $this->translate('March'),
                '4' => $this->translate('April'),
                '5' => $this->translate('Mai'),
                '6' => $this->translate('June'),
                '7' => $this->translate('July'),
                '8' => $this->translate('August'),
                '9' => $this->translate('September'),
                '10' => $this->translate('October'),
                '11' => $this->translate('November'),
                '12' => $this->translate('December'),
            ],
            'description' => $this->translate('Runs every month, if not specified'),
        ]);
    }

    protected function addCronExpression()
    {
        // Minute    Stunde    Tag des Monats    Monat    Wochentag
        $this->add(Hint::info(Html::sprintf(
            $this->translate(
                '%s: %s'
            ),
            Html::tag('strong', $this->translate('Crontab/cron expression')),
            $this->inlinePre(
                sprintf("\n .---------------- %s\n", $this->translate('minute (0 - 59)'))
                . sprintf(" |  .------------- %s\n", $this->translate('hour (0 - 23)'))
                . sprintf(" |  |  .---------- %s\n", $this->translate('day of month (1 - 31)'))
                . sprintf(" |  |  |  .------- %s\n", $this->translate('month (1 - 12) OR jan,feb,mar,apr ...'))
                . sprintf(
                    " |  |  |  |  .---- %s\n",
                    $this->translate('day of week (0 - 6) (Sunday=0 or 7) OR sun,mon,tue,...')
                )
                . " |  |  |  |  |\n"
                . " *  *  *  *  *\n"
            )
        )));

        $expressionElement = new TextWithActionButton('time_definition', [ // 'cron_expression'
            'label' => $this->translate('Expression string'),
            'required' => true,
            'description' => Html::sprintf(
                $this->translate(
                    'Crontab/cron expression. To run every Saturday at 23:45, define: %s.'
                ),
                $this->inlinePre('45 23 * * 6')
            ),
            // Patch tuesday: 0 3 * * 2#2
            'validators' => [
                new CronExpressionValidator()
            ],
        ], [
            'label' => $this->translate('Verify'),
            'title' => $this->translate('Calculate the next iterations for this expression')
        ]);
        $expressionElement->addToForm($this);
        if ($expressionElement->getButton()->hasBeenPressed()) {
            $deco = $this->getElement('time_definition')->getWrapper();
            assert($deco instanceof DdDtDecorator);
            $deco->dd()->add($this->getCalculation());
        }
    }

    protected function getCalculation(): array
    {
        $result = [];
        $dateFormat = new LocalDateFormat();
        $timeFormat = new LocalTimeFormat();
        switch ($this->getValue('recurrence_type')) {
            case 'cron':
                if ($expression = $this->getValue('time_definition')) {
                    if (! CronExpression::isValidExpression($expression)) {
                        $result[] = $this->translate('This expression is not valid');
                        break;
                    }
                    $timezone = $this->getValue('timezone');
                    if ($timezone === null) {
                        $result[] = $this->translate('Timezone is required');
                        break;
                    }
                    // Conflicts with old CronExpression in x509
                    try {
                        $cron = new CronExpression($expression);
                        $start = new \DateTimeImmutable();
                        $list = Html::tag('ul');
                        for ($i = 0; $i < 5; $i++) {
                            $next = $cron->getNextRunDate($start, 0, false, $timezone);
                            $list->add(Html::tag(
                                'li',
                                $dateFormat->getFullDay($next->getTimestamp())
                                . ' '
                                . $timeFormat->getTime($next->getTimestamp())
                            ));
                            $start = $next;
                        }
                        $result[] = Html::tag('strong', $this->translate('Next Iterations: '));
                        $result[] = $list;
                    } catch (\Exception $e) {
                        $result[] = $e->getMessage();
                    }
                }
                break;
        }

        return $result;
    }

    protected function inlinePre($content)
    {
        return Html::tag('span', [
            'class' => 'preformatted',
            'style' => 'white-space: pre'
        ], $content);
    }

    protected function enumHostLists(): array
    {
        $db = $this->store->getDb();
        $result = [];
        $query = $db->select()->from('host_list', ['uuid', 'label']);
        foreach ($db->fetchPairs($query) as $uuid => $host) {
            $result[Uuid::fromBytes($uuid)->toString()] = $host;
        }

        return $result;
    }

    /**
     * merge date/time fields into one
     */
    public function getValues(): array
    {
        $values = parent::getValues();
        foreach ($this->tsCombinations as $key) {
            if (array_key_exists($key . '_date', $values)) {
                if ($values[$key . '_date'] !== null || $values[$key . '_time']) {
                    $values[$key] = strtotime($values[$key . '_date'] . ' ' . $values[$key . '_time'] . ':00') * 1000;
                }
                unset($values[$key . '_date'], $values[$key . '_time']);
            }
        }
        if ($this->getValue('recurrence_type') === 'run_once') {
            $values['ts_not_after'] = null;
        }
        foreach ($this->timeProperties as $key) {
            if (isset($values[$key])) {
                [$hours, $minutes] = explode(':', $values[$key]);
                $values[$key] = (int) $hours * 3600 + (int) $minutes * 60;
            }
        }

        if ($this->getValue('filter_type') === 'simple_filter') {
            $simpleFilter = $this->getAllowedSimpleFilterProperties();
            $filter = Filter::matchAll();
            foreach (array_keys($simpleFilter) as $key) {
                if (null !== ($value = $this->getValue(self::FILTER_PREFIX . $key))) {
                    $filter->addFilter(Filter::expression($key, '=', $value));
                }
            }

            // This is not safe, but filters are configured by admins only
            // We need this to work around hard-coded rawurlencode in Filter
            $values['filter_definition'] = rawurldecode($filter->toQueryString());
        }

        return $values;
    }

    public function setObject(DbStorableInterface $object)
    {
        $this->object = $object;
        $this->populate($object->getProperties());
    }

    public function hasObject(): bool
    {
        return $this->object !== null;
    }

    public function getObject(): DowntimeRule
    {
        if (! $this->object instanceof DowntimeRule) {
            throw new \RuntimeException('Form has no object');
        }

        return $this->object;
    }

    /**
     * Split ts fields into date/time
     *
     * @param iterable $values
     * @return void
     */
    public function populate($values)
    {
        $values = (array) $values;
        foreach ($this->tsCombinations as $key) {
            if (isset($values[$key])) {
                $time = floor($values[$key] / 1000);
                $values[$key . '_date'] = date('Y-m-d', $time);
                $values[$key . '_time'] = date('H:i', $time);
                unset($values[$key]);
            }
        }
        foreach ($this->timeProperties as $key) {
            if (isset($values[$key]) && is_int($values[$key])) {
                $minutes = floor($values[$key] / 3600);
                $seconds = floor($values[$key] % 3600) / 60;
                $values[$key] = sprintf("%02d:%02d", $minutes, $seconds);
            }
        }

        if (! isset($values['recurrence_type']) && array_key_exists('time_definition', $values)) {
            switch ($values['time_definition']) {
                case '@daily':
                case '@weekly':
                case '@monthly':
                case '@yearly':
                    $values['recurrence_type'] = $values['time_definition'];
                    break;
                default:
                    // TODO: run_once, custom.
                    if ($values['time_definition'] !== null) {
                        $values['recurrence_type'] = 'cron';
                    } else {
                        $values['recurrence_type'] = 'run_once';
                    }
            }
        }

        $currentFilterType = $values['filter_type'] ?? $this->getPopulatedValue('filter_type');
        $currentFilter = $values['filter_definition'] ?? $this->getPopulatedValue('filter_definition');
        $hasFilter = is_string($currentFilter) && $currentFilter !== '';

        if ($currentFilterType === null) {
            if (null !== ($values['host_list_uuid'] ?? $this->getPopulatedValue('host_list_uuid'))) {
                $currentFilterType = $values['filter_type'] = 'host_list';
            } elseif ($hasFilter) {
                $currentFilterType = $values['filter_type'] = 'simple_filter';
            }
        }
        if ($currentFilterType === null) {
            $values['filter_definition'] = null;
            $values['host_list_uuid'] = null;
        } elseif ($currentFilterType === 'host_list') {
            $values['filter_definition'] = null;
        } else {
            $values['host_list_uuid'] = null;
        }
        if ($hasFilter) {
            if ($currentFilterType === 'simple_filter') {
                $filter = Filter::fromQueryString($currentFilter);
                if ($filterArray = $this->getSimpleFilterFormValues($filter)) {
                    $values['filter_type'] = 'simple_filter';
                    $values += $filterArray;
                } else {
                    $this->complexFilterIsNotSimple = true;
                    $values['filter_type'] = 'custom_filter';
                }
            }
        } elseif ($currentFilterType === 'custom_filter') {
            $filter = Filter::matchAll();
            foreach ($this->getAllowedSimpleFilterProperties() as $property => $label) {
                $prefixed = self::FILTER_PREFIX . $property;
                if (isset($values[$prefixed]) && $values[$prefixed] !== '') {
                    $filter->addFilter(Filter::expression($property, '=', $values[$prefixed]));
                    unset($values[$prefixed]);
                }
            }
            if (! $filter->isEmpty()) {
                $values['filter_definition'] = urldecode($filter->toQueryString());
            }
        }

        parent::populate($values);
    }

    protected function addTsCombination($name, $options)
    {
        if (isset($options['value'])) {
            $this->populate([
                $name => $options['value']
            ]);
            unset($options['value']);
        }
        $this->addElement('date', $name . '_date', $options);
        $deco = $this->getElement($name . '_date')->getWrapper();
        assert($deco instanceof DdDtDecorator);
        $time = $this->createElement('time', $name . '_time');
        $deco->getElementDocument()->add($time);
        $this->registerElement($time);
    }

    protected function removeTsCombination($name)
    {
        $this->remove($this->getElement("{$name}_date"));
        $this->remove($this->getElement("{$name}_time"));
    }

    public function onSuccess()
    {
        $new = $this->object === null;
        if ($new) {
            $this->uuid = Uuid::uuid4();
            $rule = new DowntimeRule();
            $formerObject = null;
        } else {
            $rule = $this->object;
            $formerObject = DowntimeRule::fromSerialization(
                $this->store->fetchObject(DowntimeRule::TABLE_NAME, $this->uuid)
            );
            $formerObject->setStored();
        }
        $properties = $this->getValues();
        $properties['uuid'] = $this->uuid->getBytes();
        if (substr($this->getElementValue('recurrence_type'), 0, 1) === '@') {
            $properties['time_definition'] = $this->getElementValue('recurrence_type');
        }
        $rule->setProperties($properties);
        $rule->recalculateConfigUuid();
        $store = new ZfDbStore($this->store->getDb());
        $subject = sprintf($this->translate('Downtime "%s"'), $properties['label']);
        if ($store->store($rule)) {
            $history = new ConfigHistory($store->getDb());
            $history->trackChanges(
                'downtime_rule',
                $formerObject,
                $rule,
                Auth::getInstance()->getUser()->getUsername()
            );
            Notification::success(sprintf(
                $new ? $this->translate('%s has been created') : $this->translate('%s has been modified'),
                $subject
            ));
        } else {
            Notification::info(sprintf($this->translate('%s has not been modified'), $subject));
        }
    }

    protected function getAllowedSimpleFilterProperties(): array
    {
        if ($this->allowedSimpleFilterProperties === null) {
            $this->allowedSimpleFilterProperties = [
                'host_name'          => $this->translate('Host Name'),
                'object_name'        => $this->translate('Object name'),
                'object_class'       => $this->translate('Object class'),
                'problem_identifier' => $this->translate('Problem identifier'),
                'message'            => $this->translate('Message')
            ];
        }

        return $this->allowedSimpleFilterProperties;
    }
}
