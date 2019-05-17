<?php

namespace Icinga\Module\Eventtracker\Controllers;

use gipfl\IcingaWeb2\CompatController;
use gipfl\IcingaWeb2\Link;
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
use Icinga\Module\Eventtracker\Web\Table\ActivityTable;
use Icinga\Web\Hook;
use ipl\Html\BaseHtmlElement;
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
            // $this->addHookedActions($incident);
            $this->showIncident($incident);
            $this->showActivities($incident, $db);
            $this->showMessage($incident);
        }
    }

    protected function showActivities(Incident $incident, $db)
    {
        $activities = new ActivityTable($db, $incident);
        if ($activities->count()) {
            $this->content()->add(Html::tag('div', [
                'class' => 'output comment'
            ], [
                Html::tag('h2', 'CHANGES'),
                Html::tag('div', ['class' => 'activities'], $activities)
            ]));
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

    protected function showObjectDetails(Incident $incident)
    {
        return [
            Html::tag('strong', 'Host: '),
            Link::create(
                $incident->get('host_name'),
                'eventtracker/events',
                // TODO: search host_name
                ['q' => $incident->get('host_name')],
                ['data-base-target' => 'col1']
            ),
            "\n",
            Html::tag('strong', 'Object: '),
            Link::create(
                $this->shorten($incident->get('object_name'), 64),
                'eventtracker/events',
                // TODO: search object_name
                ['q' => $incident->get('object_name')],
                ['data-base-target' => 'col1']
            ),
            "\n",
            Html::tag('strong', 'Class: '),
            Link::create(
                $this->shorten($incident->get('object_class'), 64),
                'eventtracker/events',
                // TODO: search object_class
                ['q' => $incident->get('object_class')],
                ['data-base-target' => 'col1']
            ),
        ];
    }

    protected function showStatusDetails(Incident $incident)
    {
        return [
            Html::tag('strong', 'Status: '),
            $incident->get('status'),
            "\n",
            Html::tag('strong', 'Priority: '),
            $this->renderPriority($incident),
            "\n",
            Html::tag('strong', 'Owner: '),
            $this->renderOwner($incident),
            "\n",
            Html::tag('strong', 'Ticket: '),
            $this->renderTicket($incident),
        ];
    }

    protected function halfPre($content, $align)
    {
        return Html::tag('pre', [
            'class' => 'output',
            'style' => "min-width: 28em; display: inline-block; width: 49% max-width: 48em; float: $align;",
        ], $content);
    }

    protected function showIncident(Incident $incident)
    {
        $preRight = $this->halfPre($this->showObjectDetails($incident), 'right');
        $preLeft = $this->halfPre($this->showStatusDetails($incident), 'left');
        $classes = [
            'output border-' . $incident->get('severity')
        ];
        if ($incident->get('status') !== 'open') {
            $classes[] = 'ack';
        }
        $this->content()->add([
            Html::tag('div', ['class' => $classes], [
                Html::tag('h2', $incident->get('severity')),
                $preLeft,
                $preRight,
                Html::tag('pre', [
                    'class' => 'output',
                    'style' => 'clear: both'
                ], [
                    Html::tag('strong', 'Events: '),
                    $this->renderTimings($incident),
                    "\n",
                    Html::tag('strong', 'Expiration: '),
                    $this->renderExpiration($incident->get('ts_expiration')),
                ])
            ]),

            // UNUSED. new EventDetailsTable($incident)
        ]);
    }

    protected function showMessage(Incident $incident)
    {
        $this->content()->add(
            Html::tag('div', [
                'class' => 'output comment'
            ], [
                Html::tag('h2', 'MESSAGE'),
                    Html::tag('pre', [
                    'style' => 'clear: both;'
                ], [
                    Html::tag('strong', $this->translate('Message') . ': '),
                    HtmlPurifier::process($incident->get('message')),
                ])
            ])
        );
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
                $this->translate('%s Events erhalten, den ersten %s and den letzten %s'),
                $count,
                $this->formatTimeAgo($incident->get('ts_first_event')),
                $this->formatTimeAgo($incident->get('ts_last_modified'))
            );
        }
    }

    protected function shorten($string, $length)
    {
        if (strlen($string) > $length) {
            return substr($string, 0, $length - 2) . '...';
        } else {
            return $string;
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

    protected function renderPriority(Incident $incident)
    {
        $result = new HtmlDocument();
        $db = DbFactory::db();

        $priority = $incident->get('priority');
        $result->add(sprintf('%-8s', $priority));

        $lower = new LinkLikeForm(
            $this->translate('[ Lower ]'),
            $this->translate('Lower priority for this issue'),
            'down-big'
        );
        $lower->on('success', function () use ($incident, $db) {
            $incident->lowerPriority();
            $incident->storeToDb($db);
            $this->getResponse()->redirectAndExit($this->url());
        });
        $lower->handleRequest($this->getServerRequest());
        $raise = new LinkLikeForm(
            $this->translate('[ Raise ]'),
            $this->translate('Raise priority for this issue'),
            'up-big'
        );
        $raise->on('success', function () use ($incident, $db) {
            $incident->raisePriority();
            $incident->storeToDb($db);
            $this->getResponse()->redirectAndExit($this->url());
        });
        $raise->handleRequest($this->getServerRequest());

        if ($priority === 'highest') {
            $raise->getElement('submit')->getAttributes()->add('disabled', 'disabled');
        }

        $result->add([$lower, $raise]);

        return $result;
    }

    protected function renderTicket(Incident $incident)
    {
        return $this->getHookedActions($incident);
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
            $incident->setOwner($myUsername);
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

    protected function addHookedActions(Incident $incident, BaseHtmlElement $target = null)
    {
        if ($target === null) {
            $target = $this->actions();
        }
        $target->add($this->getHookedActions($incident));
    }

    protected function getHookedActions(Incident $incident)
    {
        $result = [];
        /** @var EventActionsHook $impl */
        foreach (Hook::all('eventtracker/EventActions') as $impl) {
            $result[] = $impl->getIncidentActions($incident);
        }

        return $result;
    }

    // TODO: IncidentList?
    protected function addHookedMultiActions($incidents)
    {
        $incident = current($incidents);
        $actions = $this->actions();
        /** @var EventActionsHook $impl */
        foreach (Hook::all('eventtracker/EventActions') as $impl) {
            $actions->add($impl->getIncidentActions($incident));
        }
    }
}
