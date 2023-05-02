<?php

namespace Icinga\Module\Eventtracker\Web\Widget;

use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Url;
use gipfl\Translation\TranslationHelper;
use gipfl\ZfDb\Adapter\Adapter as Db;
use Icinga\Application\Hook;
use Icinga\Authentication\Auth;
use Icinga\Date\DateFormatter;
use Icinga\Module\Eventtracker\Input;
use Icinga\Module\Eventtracker\File;
use Icinga\Module\Eventtracker\Hook\EventActionsHook;
use Icinga\Module\Eventtracker\Issue;
use Icinga\Module\Eventtracker\Sender;
use Icinga\Module\Eventtracker\Web\Form\ChangePriorityForm;
use Icinga\Module\Eventtracker\Web\Form\CloseIssueForm;
use Icinga\Module\Eventtracker\Web\Form\FileUploadForm;
use Icinga\Module\Eventtracker\Web\Form\GiveOwnerShipForm;
use Icinga\Module\Eventtracker\Web\Form\ReOpenIssueForm;
use Icinga\Module\Eventtracker\Web\Form\TakeIssueForm;
use Icinga\Module\Eventtracker\Web\HtmlPurifier;
use Icinga\Util\Format;
use Icinga\Web\Response;
use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;
use ipl\Html\HtmlDocument;
use ipl\Html\ValidHtml;
use Psr\Http\Message\ServerRequestInterface;

use Ramsey\Uuid\Uuid;
use function date;

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

    /** @var Url */
    protected $requestedUrl;

    /** @var Db */
    protected $db;

    protected $showActions = true;

    public function __construct(
        Issue $issue,
        Db $db,
        ServerRequestInterface $request,
        Response $response,
        Url $requestedUrl,
        Auth $auth
    ) {
        $this->issue = $issue;
        $this->request = $request;
        $this->response = $response;
        $this->requestedUrl = $requestedUrl;
        $this->isOperator = $auth->hasPermission('eventtracker/operator');
        $this->db = $db;
    }

    protected function assemble()
    {
        $issue = $this->issue;
        $preRight = $this->halfPre($this->showAdditionalObjectDetails($issue), 'right');
        $preLeft = $this->halfPre($this->showMainDetails($issue), 'left');
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

    public function disableActions(): IssueHeader
    {
        $this->showActions = false;
        return $this;
    }

    protected function showMainDetails(Issue $issue)
    {
        return [
            Html::tag('strong', 'Host:   '),
            $issue->get('host_name') ? Link::create(
                $issue->get('host_name'),
                'eventtracker/issues',
                ['host_name' => $issue->get('host_name')],
                ['data-base-target' => 'col1']
            ) : '-',
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
            "\n",
            Html::tag('strong', 'Status: '),
            $issue->get('status'),
            $this->createOpenCloseForm($issue, $this->db),
            "\n",
            /*
            Html::tag('strong', 'Priority: '),
            $this->renderPriority($issue),
            "\n",
            */
            Html::tag('strong', 'Owner:  '),
            $this->renderOwner($issue),
            "\n",
            Html::tag('strong', 'Ticket: '),
            $this->renderTicket($issue),
            $this->renderInputAndSender($issue),
            $this->renderFiles($issue)
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

    protected function showAdditionalObjectDetails(Issue $issue)
    {
        $result = [];

        $attributes = (array) $issue->getAttributes();
        ksort($attributes);
        if (! empty($attributes)) {
            $result[] = "\n";
        }
        foreach ($attributes as $name => $value) {
            $name = preg_replace('/^[A-Za-z]+_([A-Za-z])/', '\1', $name);
            $name = str_replace('_', ' ', $name);
            if (preg_match('#^https?://[^ ]+$#', $value)) {
                if (false !== ($pos = strrpos($value, '='))) {
                    $label = substr($value, $pos + 1);
                } else {
                    $label = substr($value, strrpos(rtrim($value, '/'), '/') + 1);
                }
                $value = Html::tag('a', [
                    'href'   => $value,
                    'target' => '_blank'
                ], $label);
            }
            $result[] = Html::tag('strong', "$name: ");
            $result[] = HtmlPurifier::process($value);
            $result[] = "\n";
        }
        return $result;
    }

    protected function renderExpiration($expiration)
    {
        if ($expiration === null) {
            return $this->translate('This event will never expire');
        }

        $ts = floor($expiration / 1000);

        return Html::tag('span', [
            'class' => 'time-until',
            'title' => $this->renderNiceDate($ts),
        ], DateFormatter::timeUntil($ts));
    }

    protected function renderNiceDate($time)
    {
        return date('D d. F, Y H:i', $time);
    }

    protected function renderPriority(Issue $issue)
    {
        if (! $this->isOperator || $issue->isClosed()) {
            return  $issue->get('priority');
        }

        $form = new ChangePriorityForm($issue, $this->db);
        $form->on('success', function () {
            $this->response->redirectAndExit($this->url());
        });
        $form->handleRequest($this->request);

        return $form;
    }

    protected function url()
    {
        return Url::fromPath('eventtracker/issue', [
            'uuid' => $this->issue->getHexUuid()
        ]);
    }

    protected function renderTicket(Issue $issue)
    {
        if ($issue->get('status') === 'closed') {
            $actions = [];
        } elseif ($this->showActions) {
            $actions = $this->getHookedActions($issue);
        } else {
            $actions = [];
        }
        if (empty($actions)) {
            if ($ref = $issue->get('ticket_ref')) {
                return $ref;
            }
        }

        return $actions;
    }

    protected function renderOwner(Issue $issue)
    {
        $result = new HtmlDocument();
        $owner = $issue->get('owner');
        if ($owner === null) {
            $result->add($this->translate('Nobody in particular'));
        } else {
            $result->add($owner);
        }

        $me = $this->getMyUsername();
        if (! $this->isOperator) {
            if ($owner === $me) {
                $result->add(" (that's me!) ");
            }

            return $result;
        }

        $take = $this->createTakeOwnerShipForm($issue, $this->db);
        $give = $this->createGiveOwnerShipForm($issue, $this->db);

        if ($owner === $me) {
            $result->add([" (that's me!) ", $give]);
        } elseif ($owner) {
            $result->add([' ', $take, $give]);
        } else {
            $result->add([' ', $take]);
        }

        return $result;
    }

    protected function renderFiles(Issue $issue): ?array
    {
        $files = File::loadAllByIssue($issue, $this->db);
        if ($this->showActions || ! empty($files)) {
            $main = [
                "\n",
                Html::tag('strong', 'Files:  '),
            ];
        } else {
            return null;
        }
        if ($this->showActions) {
            if ($this->requestedUrl->getParam('upload')) {
                $main[] = Link::create($this->translate('Hide upload form'), $this->requestedUrl->without('upload'), null, [
                    'class' => 'icon-left-big',
                ]);
                $main[] = "\n";
                $form = new FileUploadForm([Uuid::fromBytes($this->issue->get('issue_uuid'))], $this->db);
                $form->on($form::ON_SUCCESS, function () {
                    $this->response->redirectAndExit($this->requestedUrl->without('upload'));
                });
                $form->handleRequest($this->request);
                $main[] = $form;
            } else {
                $main[] = Link::create($this->translate('Upload'), $this->requestedUrl->with('upload', true), null, [
                    'class' => 'icon-upload',
                ]);
            }
        }

        foreach ($files as $file) {
            $main[] = "\n        ";
            $main[] = Link::create(
                $file->get('filename'),
                'eventtracker/issue/file',
                [
                    'uuid'              => $issue->getNiceUuid(),
                    'checksum'          => bin2hex($file->get('checksum')),
                    'filename_checksum' => sha1($file->get('filename'))
                ], [
                'target' => '_blank'
                ]
            );
            $main[] = sprintf(' (%s)', Format::bytes($file->get('size')));
        }

        return $main;
    }

    protected function renderInputAndSender(Issue $issue): ?ValidHtml
    {
        $html = new HtmlDocument();

        $inputUuid = $issue->get('input_uuid');
        if ($inputUuid !== null) {
            $input = Input::byId(Uuid::fromBytes($inputUuid), $this->db);
            if ($input !== null) {
                $html->add([
                    "\n",
                    Html::tag('strong', 'Input:  '),
                    $input->get('label'),
                ]);
            }
        }

        $senderId = $issue->get('sender_id');
        if ($senderId !== null) {
            $sender = Sender::byId($senderId, $this->db);
            if ($sender !== null) {
                $html->add([
                    "\n",
                    Html::tag('strong', 'Sender: '),
                    $sender->get('sender_name'),
                ]);
            }
        }

        return $html->isEmpty() ? null : $html;
    }

    protected function getMyUsername()
    {
        return Auth::getInstance()->getUser()->getUsername();
    }

    protected function createOpenCloseForm(Issue $issue, $db)
    {
        if (! $this->isOperator || ! $this->showActions) {
            return null;
        }

        if ($issue->get('status') === 'closed') {
            // We do not allow this right now
            // $openClose = new ReOpenIssueForm($issue, $db);
            return null;
        } else {
            $openClose = new CloseIssueForm($issue, $db);
        }
        $openClose->on('success', function () {
            $this->response->redirectAndExit($this->url());
        });
        $openClose->handleRequest($this->request);

        return [' ', $openClose];
    }

    protected function createGiveOwnerShipForm(Issue $issue, $db)
    {
        if (! $this->isOperator || ! $this->showActions || $issue->isClosed()) {
            return null;
        }

        $give = new GiveOwnerShipForm($issue, $db);
        $give->on('success', function () {
            $this->response->redirectAndExit($this->url());
        });
        $give->handleRequest($this->request);

        return $give;
    }

    protected function createTakeOwnerShipForm(Issue $issue, $db)
    {
        if (! $this->isOperator || ! $this->showActions || $issue->isClosed()) {
            return null;
        }

        $take = new TakeIssueForm($issue, $db);
        $take->on('success', function () use ($issue, $db) {
            $this->response->redirectAndExit($this->url());
        });
        $take->handleRequest($this->request);

        return $take;
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
            'title' => $this->renderNiceDate($ts),
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
