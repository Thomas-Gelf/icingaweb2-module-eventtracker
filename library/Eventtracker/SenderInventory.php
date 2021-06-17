<?php

namespace Icinga\Module\Eventtracker;

use Exception;
use Zend_Db_Adapter_Abstract as Db;

class SenderInventory
{
    /** @var Db */
    protected $db;

    /** @var array */
    protected $senders;

    /** @var array */
    protected $senderImplementation;

    public function __construct(Db $db)
    {
        $this->db = $db;
        $this->refreshSenders();
    }

    /**
     * @param $senderName
     * @param $implementation
     * @return int
     * @throws Exception
     */
    public function getSenderId($senderName, $implementation)
    {
        if (isset($this->senders[$senderName])) {
            $currentImplementation = $this->senderImplementation[$senderName];
            if ($currentImplementation !== $implementation) {
                throw new \InvalidArgumentException(sprintf(
                    "Sender '%s' is not allowed to change it's implementation from '%s' to '%s'",
                    $senderName,
                    $currentImplementation,
                    $implementation
                ));
            }

            return $this->senders[$senderName];
        } else {
            return $this->createNewSender($senderName, $implementation);
        }
    }

    /**
     * @param $senderName
     * @param $implementation
     * @return int
     * @throws Exception
     */
    protected function createNewSender($senderName, $implementation)
    {
        try {
            $this->db->insert('sender', [
                'sender_name'    => $senderName,
                'implementation' => $implementation,
            ]);
            $id = (int) $this->db->lastInsertId('sender');
            $this->senders[$senderName] = $id;
            $this->senderImplementation[$senderName] = $implementation;
        } catch (Exception $e) {
            $this->refreshSenders();
            if (! isset($this->senders[$senderName])) {
                throw $e;
            }

            return $this->senders[$senderName];
        }

        return $id;
    }

    protected function refreshSenders()
    {
        $this->senders = [];
        $this->senderImplementation = [];
        $rows = $this->db->fetchAll($this->db->select()->from('sender'));
        foreach ($rows as $row) {
            $this->senders[$row->sender_name] = $row->id;
            $this->senderImplementation[$row->sender_name] = $row->implementation;
        }
    }
}
