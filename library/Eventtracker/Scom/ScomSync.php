<?php

namespace Icinga\Module\Eventtracker\Scom;

use Icinga\Module\Eventtracker\EventReceiver;
use Icinga\Module\Eventtracker\ObjectClassInventory;
use Icinga\Module\Eventtracker\SenderInventory;
use Icinga\Module\Scom\ScomEventFactory;
use Zend_Db_Adapter_Abstract as DbAdapter;
use Zend_Db_Adapter_Pdo_Mssql as Mssql;

class ScomSync
{
    protected $db;

    public function __construct(DbAdapter $db)
    {
        $this->db = $db;
    }

    /**
     * @param Mssql $db
     */
    public function syncFromDb(Mssql $db)
    {
        $query = file_get_contents(__DIR__ . '/scom-alerts.sql');
        $this->shipEvents($db->fetchAll($query));
    }

    /**
     * @param \stdClass[] $objects
     */
    public function syncFromPlainObjects($objects)
    {
        $this->shipEvents($objects);
    }

    protected function shipEvents($events)
    {
        $db = $this->db;
        $senders = new SenderInventory($db);
        $sender = $senders->getSenderId('SCOM', 'new-scom');
        $classes = new ObjectClassInventory($db);
        $factory = new ScomEventFactory($sender, $classes);
        $receiver = new EventReceiver($db);

        foreach ($events as $scomEvent) {
            $event = $factory->fromPlainObject($scomEvent);
            $issue = $receiver->processEvent($event);
        }
    }
}
