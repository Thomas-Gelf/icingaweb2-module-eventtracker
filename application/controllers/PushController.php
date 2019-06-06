<?php

namespace Icinga\Module\Eventtracker\Controllers;

use Icinga\Module\Eventtracker\MSendEventFactory;
use Icinga\Module\Eventtracker\Uuid;
use ipl\Html\Html;
use gipfl\IcingaWeb2\CompatController;
use Icinga\Module\Eventtracker\DbFactory;
use Icinga\Module\Eventtracker\EventReceiver;
use Icinga\Module\Eventtracker\MSendCommandLine;
use Icinga\Module\Eventtracker\ObjectClassInventory;
use Icinga\Module\Eventtracker\SenderInventory;

class PushController extends CompatController
{
    protected $requiresAuthentication = false;

    /**
     * @throws \Exception
     */
    public function testmsendAction()
    {
        $cmd = $this->params->get('command');
        $db = DbFactory::db();
        $senders = new SenderInventory($db);
        $classes = new ObjectClassInventory($db);
        $receiver = new EventReceiver($db);
        $mSend = new MSendCommandLine($cmd);
        $eventFactory = new MSendEventFactory($senders, $classes);
        $event = $eventFactory->fromCommandLine($mSend);
        $issue = $receiver->processEvent($event);
        $this->addSingleTab('Testing');
        $this->content()->add([
            Html::tag('h1', 'Issue'),
            Html::tag('pre', print_r($issue->getProperties(), true)),
            Html::tag('h1', 'Event'),
            Html::tag('pre', print_r($event->getProperties(), true)),
            Html::tag('h1', 'Arguments'),
            Html::tag('pre', print_r($mSend->getArguments(), true)),
            Html::tag('h1', 'Slot Values'),
            Html::tag('pre', print_r($mSend->getSlotValues(), true)),
        ]);
    }

    /**
     * @throws \Exception
     */
    public function msendAction()
    {
        $cmd = $this->getRequest()->getRawBody();
        $this->getResponse()->setHeader('Content-Type', 'text/plain');
        try {
            $db = DbFactory::db();
            $senders = new SenderInventory($db);
            $classes = new ObjectClassInventory($db);
            $receiver = new EventReceiver($db);
            $mSend = new MSendCommandLine($cmd);
            $eventFactory = new MSendEventFactory($senders, $classes);
            $event = $eventFactory->fromCommandLine($mSend);
            $issue = $receiver->processEvent($event);
            echo 'Message #1 - Evtid = ';
            echo Uuid::toHex($issue->getUuid()) . "\n";
        } catch (\Exception $e) {
            echo $e->getMessage() . "\n";
        }
        exit;
    }
}
