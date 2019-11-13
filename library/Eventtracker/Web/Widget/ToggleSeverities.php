<?php

namespace Icinga\Module\Eventtracker\Web\Widget;

use gipfl\IcingaWeb2\Url;
use Icinga\Application\Config;
use Icinga\Module\Eventtracker\ConfigHelper;
use Icinga\Module\Eventtracker\Severity;
use ipl\Html\Html;

class ToggleSeverities extends ToggleFlagList
{
    // This duplicates our CSS:
    const COLORS = [
        Severity::DEBUG         => '#cccccc',
        Severity::INFORMATIONAL => '#aaaaff;',
        Severity::NOTICE        => '#44bb77',
        Severity::WARNING       => '#ffaa44',
        Severity::ERROR         => '#ff6600',
        Severity::CRITICAL      => '#dd3300',
        Severity::ALERT         => '#aa2200',
        Severity::EMERGENCY     => '#991100',
    ];

    public function __construct(Url $url)
    {
        parent::__construct($url, 'severity');
    }

    protected function getDefaultSelection()
    {
        $selection = ConfigHelper::getList(
            Config::module('eventtracker')->get('default-filters', 'severity')
        );

        if (empty($selection)) {
            $selection = [
                Severity::EMERGENCY,
                Severity::ALERT,
                Severity::CRITICAL,
                Severity::ERROR,
                Severity::WARNING,
            ];
        } else {
            foreach ($selection as $severity) {
                Severity::assertValid($severity);
            }
        }

        return \array_combine($selection, $selection);
    }

    protected function getListLabel()
    {
        return $this->translate('Severities');
    }

    protected function getOptions()
    {
        $options = \array_reverse(Severity::ENUM, true);
        foreach ($options as $key => & $value) {
            $value = [$this->color($value), ' ', $value];
        }

        return $options;
    }

    protected function color($color)
    {
        return Html::tag('div', [
            'class' => 'square-color',
            'style' => 'background-color: ' . self::COLORS[$color]
        ]);
    }
}
