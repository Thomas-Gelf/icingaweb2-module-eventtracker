<?php

namespace Icinga\Module\Eventtracker\Controllers;

use gipfl\IcingaWeb2\CompatController;
use Icinga\Authentication\Auth;
use Icinga\Date\DateFormatter;
use Icinga\Module\Eventtracker\DbFactory;
use Icinga\Module\Eventtracker\Hook\EventActionsHook;
use Icinga\Module\Eventtracker\Incident;
use Icinga\Module\Eventtracker\SetOfIncidents;
use Icinga\Module\Eventtracker\Uuid;
use Icinga\Module\Eventtracker\Web\Form\GiveOwnerShipForm;
use Icinga\Module\Eventtracker\Web\Form\LinkLikeForm;
use Icinga\Module\Eventtracker\Web\HtmlPurifier;
use Icinga\Module\Eventtracker\Web\Table\EventDetailsTable;
use Icinga\Web\Hook;
use ipl\Html\Html;
use ipl\Html\HtmlDocument;

class EventController extends CompatController
{
    /**
     * @throws \Icinga\Exception\NotFoundError
     */
    public function indexAction()
    {
        $db = DbFactory::db();
        $this->addSingleTab('Event');
        $uuid = $this->params->get('uuid');
        if ($uuid === null) {
            $incidents = SetOfIncidents::fromUrl($this->url(), $db);
            $this->addTitle($this->translate('%d incidents'), count($incidents));
            foreach ($incidents->getIncidents() as $incident) {
                $this->showIncident($incident);
            }
        } else {
            $incident = $this->loadIncident($uuid, $db);

            $this->addTitle(sprintf(
                '%s (%s)',
                $incident->get('object_name'),
                $incident->get('host_name')
            ));
            $this->addHookedActions($incident);
            $this->showIncident($incident);
        }
    }

    /**
     * @param $uuid
     * @param \Zend_Db_Adapter_Abstract $db
     * @return Incident
     * @throws \Icinga\Exception\NotFoundError
     */
    protected function loadIncident($uuid, \Zend_Db_Adapter_Abstract $db)
    {
        return Incident::load(Uuid::toBinary($uuid), $db);
    }

    protected function showIncident(Incident $incident)
    {
        $preRight = Html::tag('pre', [
            'class' => 'output',
            'style' => 'min-width: 28em; display: inline-block; float: right;',
        ], [
            Html::tag('strong', 'Host: '),
            $incident->get('host_name'),
            "\n",
            Html::tag('strong', 'Object: '),
            $incident->get('object_name'),
            "\n",
            Html::tag('strong', 'Class: '),
            $incident->get('object_class'),
            "\n",
        ]);
        $preLeft = Html::tag('pre', [
            'class' => 'output',
            'style' => 'min-width: 28em;  float: left;',
        ], [
            Html::tag('strong', 'Status: '),
            $incident->get('status'),
            "\n",
            Html::tag('strong', 'Owner: '),
            $this->renderOwner($incident),
            "\n",
            Html::tag('strong', 'Expiration: '),
            $this->renderExpiration($incident->get('ts_expiration')),
            "\n",
        ]);
        $this->content()->add([
            Html::tag('div', ['class' => 'output border-' . $incident->get('severity')], [
                Html::tag('h2', $incident->get('severity')),
                $preLeft,
                $preRight,
                Html::tag('pre', [
                    'class' => 'output',
                    'style' => 'clear: both;'
                ], [
                    Html::tag('strong', 'Events: '),
                    $this->renderTimings($incident),
                    "\n",
                    Html::tag('strong', $this->translate('Message') . ': '),
                    HtmlPurifier::process($incident->get('message'))
                ]),
            ]),

            new EventDetailsTable($incident)
        ]);
    }

    protected function renderTimings(Incident $incident)
    {
        $count = (int) $incident->get('cnt_events');
        if ($count === 1) {
            return Html::sprintf(
                $this->translate('Got a single event %s'),
                $this->formatTimeAgo($incident->get('ts_first_event'))
            );
        } else {
            return Html::sprintf(
                // $this->translate('Got %s events, the first one %s and the last one %s'),
                $this->translate('%s Events erhalten, das erste %s and das letzte %s'),
                $count,
                $this->formatTimeAgo($incident->get('ts_first_event')),
                $this->formatTimeAgo($incident->get('ts_last_modified'))
            );
        }
    }

    protected function renderExpiration($expiration)
    {
        if ($expiration === null) {
            return $this->translate('This event will never expire');
        }

        $ts = floor($expiration / 1000);

        return Html::tag('span', [
            'class' => 'time-until',
            'title' => strftime('%A, %e. %B, %Y %H:%M', $ts),
        ], DateFormatter::timeUntil($ts));
    }

    protected function formatTimeAgo($ts)
    {
        $ts = floor($ts / 1000);

        return Html::tag('span', [
            'class' => 'time-ago',
            'title' => strftime('%A, %e. %B, %Y %H:%M', $ts),
        ], DateFormatter::timeAgo($ts));
    }

    protected function renderOwner(Incident $incident)
    {
        $myUsername = Auth::getInstance()->getUser()->getUsername();
        $result = new HtmlDocument();
        $owner = $incident->get('owner');
        if ($owner === null) {
            $result->add($this->translate('Nobody in particular'));
        } else {
            $result->add($owner);
        }
        $db = DbFactory::db();

        $take = new LinkLikeForm(
            $this->translate('[ Take ]'),
            $this->translate('Take ownership for this issue') // TODO: issue type!?
        );
        $take->on('success', function () use ($incident, $myUsername, $db) {
            $incident->set('owner', $myUsername);
            $incident->storeToDb($db);
            $this->getResponse()->redirectAndExit($this->url());
        });
        $take->handleRequest($this->getServerRequest());

        $give = new GiveOwnerShipForm($incident, $db);
        $give->on('success', function () {
            $this->getResponse()->redirectAndExit($this->url());
        });
        $give->handleRequest($this->getServerRequest());


        if ($owner === $myUsername) {
            $result->add([" (that's me!) ", "\n", $give]);
        } else {
            $result->add([' ', $take, "\n", $give]);
        }

        return $result;
    }

    protected function addHookedActions(Incident $incident)
    {
        $actions = $this->actions();
        /** @var EventActionsHook $impl */
        foreach (Hook::all('eventtracker/EventActions') as $impl) {
            $actions->add($impl->getIncidentActions($incident));
        }
    }
}
