<?php

namespace Icinga\Module\Eventtracker\Engine\Bucket;

use Evenement\EventEmitterTrait;
use gipfl\Translation\StaticTranslator;
use Icinga\Module\Eventtracker\Engine\Action\DummyTaskActions;
use Icinga\Module\Eventtracker\Engine\FormExtension;
use Icinga\Module\Eventtracker\Engine\SettingsProperty;
use Icinga\Module\Eventtracker\Engine\SimpleTaskConstructor;
use Icinga\Module\Eventtracker\Event;
use Icinga\Module\Eventtracker\Modifier\Settings;
use Icinga\Module\Eventtracker\Web\Form\Bucket\RateLimitFormExtension;
use React\EventLoop\Loop;
use React\EventLoop\TimerInterface;

class RateLimitBucket extends SimpleTaskConstructor implements BucketInterface
{
    use DummyTaskActions;
    use EventEmitterTrait;
    use SettingsProperty;

    /** @var RateLimitingBucketSlot[] */
    protected array $slots = [];

    /** @var TimerInterface[] */
    protected array $timers = [];

    public function applySettings(Settings $settings)
    {
        $this->setSettings($settings);
    }

    public function processEvent(Event $event): ?Event
    {
        $slot = $event->getChecksum();
        if (!isset($this->slots[$slot])) {
            $window = (int) $this->settings->getRequired('windowDuration');
            $this->slots[$slot] = new RateLimitingBucketSlot($this->settings);
            $this->timers[$slot] = Loop::addTimer($window, function () use ($slot) {
                unset($this->slots[$slot]);
                unset($this->timers[$slot]);
            });
        }

        return $this->slots[$slot]->processEvent($event);
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
            'Create an Issue only once in the given time frame, when reaching a certain amount of events'
        );
    }
}
