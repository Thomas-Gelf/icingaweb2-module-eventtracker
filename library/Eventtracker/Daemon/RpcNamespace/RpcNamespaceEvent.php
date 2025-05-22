<?php

namespace Icinga\Module\Eventtracker\Daemon\RpcNamespace;

use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;
use gipfl\Json\JsonString;
use gipfl\ZfDb\Adapter\Adapter as Db;
use Icinga\Module\Eventtracker\Daemon\InputAndChannelRunner;
use Icinga\Module\Eventtracker\Db\ConfigStore;
use Icinga\Module\Eventtracker\Engine\Action;
use Icinga\Module\Eventtracker\Engine\Action\ActionHelper;
use Icinga\Module\Eventtracker\Engine\Counters;
use Icinga\Module\Eventtracker\Engine\Downtime\DowntimeRunner;
use Icinga\Module\Eventtracker\Event;
use Icinga\Module\Eventtracker\Issue;
use Icinga\Module\Eventtracker\Time;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use stdClass;

use function React\Promise\Timer\timeout;

class RpcNamespaceEvent implements EventEmitterInterface
{
    use EventEmitterTrait;

    const ON_EVENT = 'event';

    const CNT_NEW = 'new';
    const CNT_IGNORED = 'ignored';
    const CNT_RECOVERED = 'recovered';
    const CNT_REFRESHED = 'refreshed';

    public const ACTION_TIMEOUT = 15;

    protected Db $db;
    protected Counters $counters;
    /** @var Action[] */
    protected array $actions = [];
    protected LoggerInterface $logger;
    protected InputAndChannelRunner $runner;
    protected DowntimeRunner $downtimeRunner;

    public function __construct(
        InputAndChannelRunner $runner,
        DowntimeRunner $downtimeRunner,
        LoggerInterface $logger,
        Db $db
    ) {
        $this->runner = $runner;
        $this->downtimeRunner = $downtimeRunner;
        $this->logger = $logger;
        $this->db = $db;
        $this->counters = new Counters();
        $this->initializeActions();
    }

    protected function initializeActions()
    {
        $actions = (new ConfigStore($this->db, $this->logger))->loadActions(['enabled' => 'y']);
        /** @var Action $action */
        foreach ($actions as $action) {
            $action->run();
        }
        $this->actions = $actions;
    }

    public function getCounters(): Counters
    {
        return $this->counters;
    }

    /**
     * @param Event|stdClass $event
     * @api
     */
    public function receiveRequest($event): PromiseInterface
    {
        $deferred = new Deferred();
        try {
            $deferred->resolve($this->processEvent($event));
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());
            $deferred->reject($e);
        }

        return $deferred->promise();
    }

    /**
     * @param UuidInterface $uuid
     * @param stdClass $event
     * @return PromiseInterface
     * @api
     */
    public function sendToInputRequest(string $uuid, stdClass $event): PromiseInterface
    {
        // Hint: Rpc Handler doesn't (yet) hydrate objects
        $inputUuid = Uuid::fromString($uuid);
        $deferred = new Deferred();
        $channel = $this->runner->getInputRunner()->getOptionalInputChannel($inputUuid);
        if ($channel) {
            $channel->process($inputUuid, $event, true);
            $deferred->resolve(true);
        } else {
            $deferred->resolve(false);
        }
        return $deferred->promise();
    }

    protected function storeRawEvent(Event $event)
    {
        $this->db->insert('raw_event', [
            // 'input_uuid'        => $inputUuid->getBytes(),
            'event_uuid'        => $event->get('uuid'), // TODO: place on event
            'ts_received'       => (int) microtime(true) * 1000,
            // 'failed', 'ignored', 'issue_created', 'issue_refreshed', 'issue_acknowledged', 'issue_closed'
            'processing_result' => 'received', // impossible to tell?!
            'error_message'     => null,
            'raw_input'         => JsonString::encode($event),
            'input_format'      => 'json',
        ]);
    }

    /**
     * Runs for legacy Senders only (-> TODO: verify)
     */
    protected function processEvent($event): ?Issue
    {
        $event = Event::fromSerialization($event);
        // $event->set('uuid', Uuid::uuid4()->getBytes());
        // $this->storeRawEvent($event);
        $issue = Issue::loadIfEventExists($event, $this->db);
        if ($event->hasBeenCleared()) {
            if ($issue) {
                $this->counters->increment(self::CNT_RECOVERED);
                $issue->recover($event, $this->db);
                // TODO: Tell DowntimeRunner?
            } else {
                $this->counters->increment(self::CNT_IGNORED);
                return null;
            }
        } elseif ($event->isProblem()) {
            if ($issue) {
                $this->counters->increment(self::CNT_REFRESHED);
                $issue->setPropertiesFromEvent($event);
            } else {
                $issue = Issue::createFromEvent($event);
                $this->counters->increment(self::CNT_NEW);
            }
            if ($issue->get('severity') === null) {
                $issue->set('severity', 'notice');
            }
            if ($rule = $this->downtimeRunner->getDowntimeRuleForIssueIfAny($issue)) {
                $now = Time::unixMilli();
                $issue->set('status', 'in_downtime');
                $issue->set('downtime_rule_uuid', $rule->get('uuid'));
                $issue->set('downtime_config_uuid', $rule->get('config_uuid'));
                $issue->set('ts_downtime_triggered', $now);
                $issue->storeToDb($this->db);
                $this->downtimeRunner->store->logDowntimeActivation($issue, $rule, $now);
            } else {
                $issue->storeToDb($this->db);
            }
        } elseif ($issue) {
            $this->counters->increment(self::CNT_RECOVERED);
            $issue->recover($event, $this->db);
            // TODO: Tell DowntimeRunner?

            return null;
        } else {
            $this->counters->increment(self::CNT_IGNORED);
            return null;
        }

        if ($issue->hasBeenCreatedNow()) {
            $actions = ActionHelper::processIssue($this->actions, $issue, $this->db, $this->logger);
            timeout($actions, 15);
        }

        return $issue;
    }
}
