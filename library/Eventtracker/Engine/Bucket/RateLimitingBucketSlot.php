<?php

namespace Icinga\Module\Eventtracker\Engine\Bucket;

use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;
use Icinga\Module\Eventtracker\ConfigHelper;
use Icinga\Module\Eventtracker\Event;
use Icinga\Module\Eventtracker\Modifier\Settings;

class RateLimitingBucketSlot implements EventEmitterInterface
{
    use EventEmitterTrait;

    /** @var ?Event */
    protected $event = null;

    /** @var int */
    protected $count = 0;

    /** @var int */
    protected $thresholdCount;

    /** @var string */
    protected $attributeSource;

    /** @var string */
    protected $message;

    public function __construct(Settings $settings)
    {
        $this->thresholdCount = (int) $settings->getRequired('thresholdCount');
        $this->attributeSource = (string) $settings->getRequired('attributeSource');
        $this->message = (string) $settings->getRequired('message');
    }

    public function processEvent(Event $event): ?Event
    {
        if ($this->event === null || $this->attributeSource === 'last_event') {
            $this->event = $event;
        } else {
            $event = $this->event;
        }
        $this->count++;
        // Hint: equal, not greater than - this is correct.
        //       the bucket stays active for the whole window, and emits only one event
        if ($this->count === $this->thresholdCount) {
            $event->set('message', ConfigHelper::fillPlaceholders($this->message, $event));
            return $event;
        }

        return null;
    }
}
