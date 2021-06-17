<?php

namespace Icinga\Module\Eventtracker\Syslog;

use Icinga\Module\Eventtracker\EventReceiver;
use Icinga\Module\Eventtracker\SenderInventory;
use Zend_Db_Adapter_Abstract as DbAdapter;

class SyslogProcessor
{
    /** @var DbAdapter */
    protected $db;

    /** @var EventReceiver */
    protected $eventReceiver;

    public function __construct(DbAdapter $db)
    {
        $this->eventReceiver = new EventReceiver($db);
        $this->db = $db;
    }

    /**
     * @param $line
     * @return \Icinga\Module\Eventtracker\Issue|null
     * @throws \Zend_Db_Adapter_Exception
     */
    public function processSyslogLine($line)
    {
        $senders = new SenderInventory($this->db);
        $factory = new SyslogEventFactory($senders->getSenderId('OEM Syslog', 'oem-syslog'));
        $entry = SyslogParser::parseLine($line);
        $event = $factory->fromPlainObject($entry);
// TODO: Ignoring for now, shouldn't remain so:
        if ($event->get('sender_event_id') === null) {
// var_dump("NO EVENT ID: $line\n");
            return null;
        }
        $counters = $this->eventReceiver->getCounters()->snapshot();
        $result = $this->eventReceiver->processEvent($event);
        $counterDiff = $this->eventReceiver->getCounters()->calculateDiffFrom($counters);
        if (! $counterDiff->isEmpty()) {
            // echo $counterDiff->renderSummary() . "\n";
            echo "Issue " . implode(', ', $counterDiff->listNames()) . "\n";
        }
        return $result;
    }
}
