<?php

namespace Icinga\Module\Eventtracker\IdoMonitoring;

use Icinga\Module\Monitoring\Command\Object\AcknowledgeProblemCommand;
use Icinga\Module\Monitoring\Command\Transport\CommandTransport;
use Icinga\Module\Monitoring\Exception\CommandTransportException;
use Icinga\Module\Monitoring\Object\MonitoredObject;
use RuntimeException;

class IcingaCommandPipe
{
    public function acknowledgeObject(string $author, string $message, MonitoredObject $object): bool
    {
        if ($object->acknowledged) {
            return false;
        }

        $cmd = new AcknowledgeProblemCommand();
        $cmd->setObject($object)
            ->setAuthor($author)
            ->setComment($message)
            ->setPersistent(false)
            ->setSticky(false)
            ->setNotify(false)
            ;

        try {
            $transport = new CommandTransport();
            $transport->send($cmd);
        } catch (CommandTransportException $e) {
            throw new RuntimeException($e->getMessage(), 0, $e);
        }

        return true;
    }
}
