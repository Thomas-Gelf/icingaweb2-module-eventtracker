<?php

namespace Icinga\Module\Eventracker\IcingaDb;

use ArrayIterator;
use Icinga\Module\Icingadb\Command\Object\AcknowledgeProblemCommand;
use Icinga\Module\Icingadb\Command\Transport\CommandTransport;
use Icinga\Module\Icingadb\Command\Transport\CommandTransportException;
use Icinga\Module\Icingadb\Model\Service;
use ipl\Orm\Model;
use RuntimeException;

class CommandPipe
{
    public function acknowledgeObject($author, $message, Model $object)
    {
        /** @var Service $object */
        if ($object->state->is_acknowledged) {
            return false;
        }

        $cmd = new AcknowledgeProblemCommand();
        $cmd->setObjects(new ArrayIterator([$object]))
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
