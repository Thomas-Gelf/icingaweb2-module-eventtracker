<?php

namespace Icinga\Module\Eventtracker\Engine\Bucket;

use Evenement\EventEmitterTrait;
use gipfl\Translation\StaticTranslator;
use Icinga\Module\Eventtracker\Engine\Action\DummyTaskActions;
use Icinga\Module\Eventtracker\Engine\FormExtension;
use Icinga\Module\Eventtracker\Engine\SettingsProperty;
use Icinga\Module\Eventtracker\Engine\SimpleTaskConstructor;
use Icinga\Module\Eventtracker\Engine\Task;
use Icinga\Module\Eventtracker\Issue;
use Icinga\Module\Eventtracker\Modifier\Settings;
use Icinga\Module\Eventtracker\Web\Form\Bucket\RateLimitFormExtension;

class RateLimitBucket extends SimpleTaskConstructor implements Task
{
    use DummyTaskActions;
    use EventEmitterTrait;
    use SettingsProperty;

    public function applySettings(Settings $settings)
    {
        $this->setSettings($settings);
    }

    public function processIssue(Issue $issue): ?Issue
    {
    }

    public static function getFormExtension(): FormExtension
    {
        return new RateLimitFormExtension();
    }

    public static function getLabel()
    {
        return StaticTranslator::get()->translate('Rate Limit');
    }

    public static function getDescription()
    {
        return StaticTranslator::get()->translate(
            'Create an Issue n'
        );
    }
}
