<?php

namespace Icinga\Module\Eventtracker\Controllers;

use gipfl\IcingaWeb2\CompatController;
use gipfl\IcingaWeb2\Link;
use Icinga\Authentication\Auth;
use Icinga\Date\DateFormatter;
use Icinga\Module\Eventtracker\DbFactory;
use Icinga\Module\Eventtracker\Hook\EventActionsHook;
use Icinga\Module\Eventtracker\Issue;
use Icinga\Module\Eventtracker\Priority;
use Icinga\Module\Eventtracker\SetOfIssues;
use Icinga\Module\Eventtracker\Uuid;
use Icinga\Module\Eventtracker\Web\Form\GiveOwnerShipForm;
use Icinga\Module\Eventtracker\Web\Form\LinkLikeForm;
use Icinga\Module\Eventtracker\Web\HtmlPurifier;
use Icinga\Module\Eventtracker\Web\Table\ActivityTable;
use Icinga\Web\Hook;
use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;
use ipl\Html\HtmlDocument;

class IssueController extends CompatController
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
            $issues = SetOfIssues::fromUrl($this->url(), $db);
            $this->addTitle($this->translate('%d issues'), count($issues));
            foreach ($issues->getIssues() as $issue) {
                $this->showIssue($issue);
            }
        } else {
            $issue = $this->loadIssue($uuid, $db);

            $this->addTitle(sprintf(
                '%s (%s)',
                $issue->get('object_name'),
                $issue->get('host_name')
            ));
            // $this->addHookedActions($issue);
            $this->showIssue($issue);
            $this->showActivities($issue, $db);
            $this->showMessage($issue);
        }
    }

    protected function showActivities(Issue $issue, $db)
    {
        $activities = new ActivityTable($db, $issue);
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
     * @return Issue
     * @throws \Icinga\Exception\NotFoundError
     */
    protected function loadIssue($uuid, \Zend_Db_Adapter_Abstract $db)
    {
        return Issue::load(Uuid::toBinary($uuid), $db);
    }

    protected function showObjectDetails(Issue $issue)
    {
        return [
            Html::tag('strong', 'Host:   '),
            Link::create(
                $issue->get('host_name'),
                'eventtracker/issues',
                ['host_name' => $issue->get('host_name')],
                ['data-base-target' => 'col1']
            ),
            "\n",
            Html::tag('strong', 'Object: '),
            Link::create(
                $this->shorten($issue->get('object_name'), 64),
                'eventtracker/issues',
                ['object_name' => $issue->get('object_name')],
                ['data-base-target' => 'col1']
            ),
            "\n",
            Html::tag('strong', 'Class:  '),
            Link::create(
                $this->shorten($issue->get('object_class'), 64),
                'eventtracker/issues',
                ['object_class' => $issue->get('object_class')],
                ['data-base-target' => 'col1']
            ),
        ];
    }

    protected function showStatusDetails(Issue $issue)
    {
        return [
            Html::tag('strong', 'Status: '),
            $issue->get('status'),
            "\n",
            Html::tag('strong', 'Priority: '),
            $this->renderPriority($issue),
            "\n",
            Html::tag('strong', 'Owner: '),
            $this->renderOwner($issue),
            "\n",
            Html::tag('strong', 'Ticket: '),
            $this->renderTicket($issue),
        ];
    }

    protected function halfPre($content, $align)
    {
        return Html::tag('pre', [
            'class' => 'output',
            'style' => "min-width: 28em; display: inline-block; width: 49% max-width: 48em; float: $align;",
        ], $content);
    }

    protected function showIssue(Issue $issue)
    {
        $preRight = $this->halfPre($this->showObjectDetails($issue), 'right');
        $preLeft = $this->halfPre($this->showStatusDetails($issue), 'left');
        $classes = [
            'output border-' . $issue->get('severity')
        ];
        if ($issue->get('status') !== 'open') {
            $classes[] = 'ack';
        }
        $this->content()->add([
            Html::tag('div', ['class' => $classes], [
                Html::tag('h2', $issue->get('severity')),
                $preLeft,
                $preRight,
                Html::tag('pre', [
                    'class' => 'output',
                    'style' => 'clear: both'
                ], [
                    Html::tag('strong', 'Events:     '),
                    $this->renderTimings($issue),
                    "\n",
                    Html::tag('strong', 'Expiration: '),
                    $this->renderExpiration($issue->get('ts_expiration')),
                ])
            ]),

            // UNUSED. new EventDetailsTable($issue)
        ]);
    }

    protected function showMessage(Issue $issue)
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
                    HtmlPurifier::process($issue->get('message')),
                ])
            ])
        );
    }

    protected function renderTimings(Issue $issue)
    {
        $count = (int) $issue->get('cnt_events');
        if ($count === 1) {
            return Html::sprintf(
                $this->translate('Got a single event %s'),
                $this->formatTimeAgo($issue->get('ts_first_event'))
            );
        } else {
            return Html::sprintf(
                $this->translate('Got %s events, the first one %s and the last one %s'),
                $count,
                $this->formatTimeAgo($issue->get('ts_first_event')),
                $this->formatTimeAgo($issue->get('ts_last_modified'))
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

    protected function renderPriority(Issue $issue)
    {
        $result = new HtmlDocument();
        $db = DbFactory::db();

        $priority = $issue->get('priority');
        $result->add(sprintf('%-8s', $priority));

        if (! $this->isOperator()) {
            return $result;
        }

        $lower = new LinkLikeForm(
            $this->translate('[ Lower ]'),
            $this->translate('Lower priority for this issue'),
            'down-big'
        );
        $lower->on('success', function () use ($issue, $db) {
            $issue->lowerPriority();
            $issue->storeToDb($db);
            $this->getResponse()->redirectAndExit($this->url());
        });
        $lower->handleRequest($this->getServerRequest());
        if (Priority::isLowest($priority)) {
            $lower->getElement('submit')->getAttributes()->add('disabled', 'disabled');
        }

        $raise = new LinkLikeForm(
            $this->translate('[ Raise ]'),
            $this->translate('Raise priority for this issue'),
            'up-big'
        );
        $raise->on('success', function () use ($issue, $db) {
            $issue->raisePriority();
            $issue->storeToDb($db);
            $this->getResponse()->redirectAndExit($this->url());
        });
        $raise->handleRequest($this->getServerRequest());

        if (Priority::isHighest($priority)) {
            $raise->getElement('submit')->getAttributes()->add('disabled', 'disabled');
        }

        $result->add([$lower, $raise]);

        return $result;
    }

    protected function renderTicket(Issue $issue)
    {
        return $this->getHookedActions($issue);
    }

    protected function renderOwner(Issue $issue)
    {
        $myUsername = Auth::getInstance()->getUser()->getUsername();
        $result = new HtmlDocument();
        $owner = $issue->get('owner');
        if ($owner === null) {
            $result->add($this->translate('Nobody in particular'));
        } else {
            $result->add($owner);
        }
        $db = DbFactory::db();

        if (! $this->isOperator()) {
            if ($owner === $myUsername) {
                $result->add(" (that's me!) ");
            }

            return $result;
        }

        $take = new LinkLikeForm(
            $this->translate('[ Take ]'),
            $this->translate('Take ownership for this issue') // TODO: issue type!?
        );
        $take->on('success', function () use ($issue, $myUsername, $db) {
            $issue->setOwner($myUsername);
            $issue->storeToDb($db);
            $this->getResponse()->redirectAndExit($this->url());
        });
        $take->handleRequest($this->getServerRequest());

        $give = new GiveOwnerShipForm($issue, $db);
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

    protected function addHookedActions(Issue $issue, BaseHtmlElement $target = null)
    {
        if ($target === null) {
            $target = $this->actions();
        }
        $target->add($this->getHookedActions($issue));
    }

    protected function getHookedActions(Issue $issue)
    {
        $result = [];
        /** @var EventActionsHook $impl */
        foreach (Hook::all('eventtracker/EventActions') as $impl) {
            $result[] = $impl->getIssueActions($issue);
        }

        return $result;
    }

    // TODO: IssueList?
    protected function addHookedMultiActions($issues)
    {
        $issue = current($issues);
        $actions = $this->actions();
        /** @var EventActionsHook $impl */
        foreach (Hook::all('eventtracker/EventActions') as $impl) {
            $actions->add($impl->getIssueActions($issue));
        }
    }

    protected function isOperator()
    {
        return $this->hasPermission('eventtracker/operator');
    }
}
