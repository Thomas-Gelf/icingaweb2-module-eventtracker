<?php

namespace Icinga\Module\Eventtracker\Web\Widget;

use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Url;
use gipfl\Translation\TranslationHelper;
use Icinga\Authentication\Auth;
use Icinga\Date\DateFormatter;
use Icinga\Module\Eventtracker\DbFactory;
use Icinga\Module\Eventtracker\Hook\EventActionsHook;
use Icinga\Module\Eventtracker\Issue;
use Icinga\Module\Eventtracker\Priority;
use Icinga\Module\Eventtracker\Web\Form\GiveOwnerShipForm;
use Icinga\Module\Eventtracker\Web\Form\LinkLikeForm;
use Icinga\Web\Hook;
use Icinga\Web\Response;
use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;
use ipl\Html\HtmlDocument;
use Psr\Http\Message\ServerRequestInterface;

class IssueHeader extends BaseHtmlElement
{
    use TranslationHelper;

    protected $tag = 'div';

    protected $defaultAttributes = [
    ];

    /** @var Issue */
    protected $issue;

    protected $isOperator = false;

    /** @var ServerRequestInterface */
    protected $request;

    /** @var Response */
    protected $response;

    public function __construct(
        Issue $issue,
        ServerRequestInterface $request,
        Response $response,
        Auth $auth
    ) {
        $this->issue = $issue;
        $this->request = $request;
        $this->response = $response;
        $this->isOperator = $auth->hasPermission('eventtracker/operator');
    }

    protected function assemble()
    {
        $issue = $this->issue;
        $preRight = $this->halfPre($this->showObjectDetails($issue), 'right');
        $preLeft = $this->halfPre($this->showStatusDetails($issue), 'left');
        $classes = [
            'output border-' . $issue->get('severity')
        ];
        if ($issue->get('status') !== 'open') {
            $classes[] = 'ack';
        }
        $this->add([
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
        ]);
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

    protected function renderPriority(Issue $issue)
    {
        $result = new HtmlDocument();
        $db = DbFactory::db();

        $priority = $issue->get('priority');
        $result->add(sprintf('%-8s', $priority));

        if (! $this->isOperator) {
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
            $this->response->redirectAndExit($this->url());
        });
        $lower->handleRequest($this->request);
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
            $this->response->redirectAndExit($this->url());
        });
        $raise->handleRequest($this->request);

        if (Priority::isHighest($priority)) {
            $raise->getElement('submit')->getAttributes()->add('disabled', 'disabled');
        }

        $result->add([$lower, $raise]);

        return $result;
    }

    protected function url()
    {
        return Url::fromPath('eventtracker/issue', [
            'uuid' => $this->issue->getHexUuid()
        ]);
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

        if (! $this->isOperator) {
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
            $this->response->redirectAndExit($this->url());
        });
        $take->handleRequest($this->request);

        $give = new GiveOwnerShipForm($issue, $db);
        $give->on('success', function () {
            $this->response->redirectAndExit($this->url());
        });
        $give->handleRequest($this->request);


        if ($owner === $myUsername) {
            $result->add([" (that's me!) ", "\n", $give]);
        } else {
            $result->add([' ', $take, "\n", $give]);
        }

        return $result;
    }

    protected function halfPre($content, $align)
    {
        return Html::tag('pre', [
            'class' => 'output',
            'style' => "min-width: 28em; display: inline-block; width: 49% max-width: 48em; float: $align;",
        ], $content);
    }

    protected function formatTimeAgo($ts)
    {
        $ts = floor($ts / 1000);

        return Html::tag('span', [
            'class' => 'time-ago',
            'title' => strftime('%A, %e. %B, %Y %H:%M', $ts),
        ], DateFormatter::timeAgo($ts));
    }

    protected function shorten($string, $length)
    {
        if (strlen($string) > $length) {
            return substr($string, 0, $length - 2) . '...';
        } else {
            return $string;
        }
    }


    // TODO: Unused.
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
}
