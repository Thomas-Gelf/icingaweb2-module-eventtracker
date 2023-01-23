<?php

namespace Icinga\Module\Eventtracker;

use gipfl\ZfDb\Adapter\Adapter as Db;
use Icinga\Module\Eventtracker\Db\ConfigStore;
use Icinga\Module\Eventtracker\Engine\Action;
use Icinga\Module\Eventtracker\Engine\Action\ActionHelper;
use Icinga\Module\Eventtracker\Engine\Counters;
use React\EventLoop\Factory;
use function Clue\React\Block\await as block_await;

class EventReceiver
{
    const CNT_NEW = 'new';
    const CNT_IGNORED = 'ignored';
    const CNT_RECOVERED = 'recovered';
    const CNT_REFRESHED = 'refreshed';

    public const ACTION_TIMEOUT = 15;

    /** @var Db */
    protected $db;

    protected $counters;

    /** @var bool */
    protected $runActions;

    public function __construct(Db $db, $runActions = true)
    {
        $this->db = $db;
        $this->counters = new Counters();
        $this->runActions = $runActions;
    }

    /**
     * @param Event $event
     * @return Issue|null
     * @throws \gipfl\ZfDb\Adapter\Exception\AdapterException
     * @throws \gipfl\ZfDb\Statement\Exception\StatementException
     */
    public function processEvent(Event $event)
    {
        $issue = Issue::loadIfEventExists($event, $this->db);
        if ($event->hasBeenCleared()) {
            if ($issue) {
                $this->counters->increment(self::CNT_RECOVERED);
                $issue->recover($event, $this->db);
            } else {
                $this->counters->increment(self::CNT_IGNORED);
                return null;
            }
        } elseif ($event->isProblem()) {
            if ($issue) {
                $this->counters->increment(self::CNT_REFRESHED);
                $issue->setPropertiesFromEvent($event);
            } else {
                $issue = Issue::create($event, $this->db);
                $this->counters->increment(self::CNT_NEW);
            }
            $issue->storeToDb($this->db);
        } elseif ($issue) {
            $this->counters->increment(self::CNT_RECOVERED);
            $issue->recover($event, $this->db);

            return null;
        } else {
            $this->counters->increment(self::CNT_IGNORED);
        }

        if ($this->runActions && $issue->hasBeenCreatedNow()) {
            $actions = (new ConfigStore($this->db))->loadActions(['enabled' => 'y']);
            $loop = Factory::create();
            /** @var Action $action */
            foreach ($actions as $action) {
                $action->run($loop);
            }
            block_await(ActionHelper::processIssue($actions, $issue, $this->db), $loop, static::ACTION_TIMEOUT);
        }

        return $issue;
    }

    public function getCounters()
    {
        return $this->counters;
    }
}
