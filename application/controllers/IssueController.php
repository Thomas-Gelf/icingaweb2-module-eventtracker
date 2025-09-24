<?php

namespace Icinga\Module\Eventtracker\Controllers;

use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Url;
use gipfl\Web\Widget\Hint;
use Icinga\Application\Hook;
use Icinga\Exception\Http\HttpNotFoundException;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Eventtracker\Data\PlainObjectRenderer;
use Icinga\Module\Eventtracker\Engine\EnrichmentHelper;
use Icinga\Module\Eventtracker\File;
use Icinga\Module\Eventtracker\Hook\EventActionsHook;
use Icinga\Module\Eventtracker\Issue;
use Icinga\Module\Eventtracker\IssueHistory;
use Icinga\Module\Eventtracker\SetOfIssues;
use Icinga\Module\Eventtracker\Web\Form\CloseIssueForm;
use Icinga\Module\Eventtracker\Web\Form\FileUploadForm;
use Icinga\Module\Eventtracker\Web\Form\TakeIssueForm;
use Icinga\Module\Eventtracker\Web\Widget\IdoDetails;
use Icinga\Module\Eventtracker\Web\Widget\IssueActivities;
use Icinga\Module\Eventtracker\Web\Widget\IssueDetails;
use Icinga\Module\Eventtracker\Web\Widget\IssueHeader;
use ipl\Html\Html;
use Ramsey\Uuid\Uuid;

use function Clue\React\Block\awaitAll;

class IssueController extends Controller
{
    use AsyncControllerHelper;
    use RestApiMethods;

    protected $requiresAuthentication = false;

    public function init()
    {
        parent::init();
        if ($this->getRequest()->isApiRequest()) {
            return;
        } else {
            $this->assertPermission('module/eventtracker');
        }

        $this->tabs()
            ->add('issue', [
                'label' => $this->translate('Issue'),
                'url'   => Url::fromPath('eventtracker/issue')->setParam('uuid', $this->params->get('uuid'))
            ])
            ->add('raw', [
                'label' => $this->translate('Raw Data'),
                'url'   => Url::fromPath('eventtracker/issue/raw')->setParam('uuid', $this->params->get('uuid'))
            ]);
    }

    public function indexAction()
    {
        $this->notForApi();
        $this->tabs()->activate('issue');
        $db = $this->db();
        $uuid = $this->params->get('uuid');
        try {
            $activeTimeSlots = (array) $this->syncRpcCall('eventtracker.getActiveTimeSlots');
        } catch (\Exception $e) {
            $this->content()->add(Hint::warning(
                $this->translate('Got no active time slot info, is the daemon running?')
            ));
            $activeTimeSlots = [];
        }
        $upload = $this->url()->shift('upload');
        if (! $upload) {
            $this->setAutorefreshInterval(30);
        }
        if ($uuid === null) {
            $issues = SetOfIssues::fromUrl($this->url(), $db);
            $count = \count($issues);
            $this->addTitle($this->translate('%d issues'), $count);
            $maxIssues = $this->Config()->get('ui', 'multiselect_max_issues', 50);
            if ($count > $maxIssues) {
                $this->content()->add(Hint::warning(
                    \sprintf($this->translate('Please select no more than %s issues'), $maxIssues)
                ));

                return;
            }
            if ($this->Auth()->hasPermission('eventtracker/operator')) {
                $this->actions()->add(
                    (new CloseIssueForm($issues, $db))->on('success', function () use ($count) {
                        $this->getResponse()->redirectAndExit(
                            Url::fromPath('eventtracker/issue/closed', ['cnt' => $count])
                        );
                    })->handleRequest($this->getServerRequest())
                );

                $this->actions()->add((new TakeIssueForm($issues, $db))->on('success', function () {
                    $this->getResponse()->redirectAndExit($this->url());
                })->handleRequest($this->getServerRequest()));
            }

            if ($upload) {
                $this->actions()->add(
                    Link::create($this->translate('Hide upload form'), $this->url()->without('upload'), null, [
                        'class' => 'icon-left-big',
                    ])
                );
                $form = new FileUploadForm($issues->getUuidObjects(), $db);
                $form->on($form::ON_SUCCESS, function () {
                    $this->redirectNow($this->url()->without('upload'));
                });
                $form->handleRequest($this->getServerRequest());
                $this->content()->prepend($form);
            } else {
                $this->actions()->add(
                    Link::create($this->translate('Upload'), $this->url()->with('upload', true), null, [
                        'class' => 'icon-upload',
                    ])
                );
            }

            /** @var EventActionsHook $impl */
            foreach (Hook::all('eventtracker/EventActions') as $impl) {
                $this->actions()->add($impl->getIssuesActions($issues));
            }
            foreach ($issues->getIssues() as $issue) {
                $this->content()->add((new IssueHeader(
                    $issue,
                    $this->db(),
                    $this->getServerRequest(),
                    $this->getResponse(),
                    $this->url(),
                    $this->Auth()
                ))->disableActions()->setActiveTimeSlots($activeTimeSlots));
            }
        } else {
            $binaryUuid = Uuid::fromString($uuid)->getBytes();
            if ($issue = Issue::loadIfExists($binaryUuid, $db)) {
                $this->showIssue($issue, $activeTimeSlots);
            } elseif ($reason = IssueHistory::getReasonIfClosed($binaryUuid, $db)) {
                $this->addTitle($this->translate('Issue has been closed'));
                $this->content()->add(Hint::info($this->getCloseDetails($reason)));
                $issue = Issue::loadFromHistory($binaryUuid, $db);
                $this->showIssue($issue, $activeTimeSlots);
            } else {
                $this->addTitle($this->translate('Not found'));
                $this->content()->add(Hint::error($this->translate('There is no such issue')));
            }
        }
    }

    protected function getCloseDetails($row): string
    {
        switch ($row->close_reason) {
            case IssueHistory::REASON_MANUAL:
                if ($row->closed_by === null) {
                    return $this->translate('This issue has been closed manually');
                }
                return sprintf($this->translate('This issue has been closed by %s'), $row->closed_by);
            case IssueHistory::REASON_RECOVERY:
                return $this->translate('This issue has recovered');
            case IssueHistory::REASON_EXPIRATION:
                return $this->translate('This issue has expired');
            case null:
                return $this->translate('This issue has been closed, reason unknown');
            default:
                return sprintf($this->translate('This issue has been closed, invalid reason: %s'), $row->close_reason);
        }
    }

    public function fileAction()
    {
        $this->notForApi();
        $uuid = Uuid::fromString($this->params->getRequired('uuid'));
        $checksum = $this->params->getRequired('checksum');
        $filenameChecksum = $this->params->getRequired('filename_checksum');

        $file = File::loadByIssueUuidAndChecksum($uuid, hex2bin($checksum), hex2bin($filenameChecksum), $this->db());
        if ($file === null) {
            throw new NotFoundError('File not found');
        }

        $this->_helper->viewRenderer->disable();
        $this->_helper->layout()->disableLayout();

        $this->getResponse()->setHeader(
            'Cache-Control',
            'public, max-age=1814400, stale-while-revalidate=604800',
            true
        );

        if ($this->getRequest()->getHeader('Cache-Control') !== 'no-cache'
            && $this->getRequest()->getHeader('If-None-Match') === $checksum
        ) {
            $this
                ->getResponse()
                ->setHttpResponseCode(304);
        } else {
            $this
                ->getResponse()
                ->setHeader('ETag', $checksum, true)
                ->setHeader('Content-Type', $file->get('mime_type'), true)
                ->setHeader('Content-Disposition', sprintf('attachment; filename="%s"', $file->get('filename')))
                ->setBody($file->get('data'));
        }
    }

    public function rawAction()
    {
        $this->notForApi();
        $this->tabs()->activate('raw');
        $binaryUuid = Uuid::fromString($this->params->getRequired('uuid'))->getBytes();
        $db = $this->db();
        $issue = Issue::loadIfExists($binaryUuid, $db);
        if ($issue === null) {
            if (IssueHistory::exists($binaryUuid, $db)) {
                $issue = Issue::loadFromHistory($binaryUuid, $db);
            } else {
                throw new HttpNotFoundException($this->translate('Issue not found'));
            }
        }

        if ($hostname = $issue->get('host_name')) {
            $this->addTitle(sprintf(
                '%s (%s)',
                $issue->get('object_name'),
                $hostname
            ));
        } else {
            $this->addTitle($issue->get('object_name'));
        }

        $this->content()->add([
            Html::tag('h3', $this->translate('Raw')),
            Html::tag('pre', [
                'class' => 'plain-object'
            ], PlainObjectRenderer::render(EnrichmentHelper::enrichIssue($issue, $db))),
            Html::tag('h3', $this->translate('Raw for filters')),
            Html::tag('pre', [
                'class' => 'plain-object'
            ], PlainObjectRenderer::render(EnrichmentHelper::enrichIssueForFilter($issue, $db)))
        ]);
    }

    protected function showIssue(Issue $issue, array $activeTimeSlots)
    {
        $db = $this->db();
        if ($hostname = $issue->get('host_name')) {
            $this->addTitle(sprintf(
                '%s (%s)',
                self::cleanTitleString($issue->get('object_name')),
                self::cleanTitleString($hostname)
            ));
        } else {
            $this->addTitle(self::cleanTitleString($issue->get('object_name')));
        }
        // $this->addHookedActions($issue);
        $this->content()->add([
            (new IssueHeader(
                $issue,
                $this->db(),
                $this->getServerRequest(),
                $this->getResponse(),
                $this->url(),
                $this->Auth()
            ))->setActiveTimeSlots($activeTimeSlots),
            new IdoDetails($issue, $db),
            new IssueDetails($issue),
            new IssueActivities($issue, $db),
        ]);
    }

    protected static function cleanTitleString(string $string): string
    {
        return str_replace(["\r", "\n"], '_', $string);
    }

    /**
     * @throws NotFoundError
     */
    public function acknowledgeAction()
    {
        if (! $this->getRequest()->isApiRequest()) {
            throw new NotFoundError('Not found');
        }

        // TODO: implement.
    }

    /**
     * @throws NotFoundError
     */
    public function closeAction()
    {
        if (!$this->getRequest()->isApiRequest() || !$this->getRequest()->isPost()) {
            throw new NotFoundError('Not found');
        }
        $this->runForApi(function () {
            $this->closeForApi();
        });
    }

    public function closeForApi()
    {
        if (! $this->checkBearerToken('issue/close')) {
            return;
        }
        $uuids = $this->findApiRequestIssues();
        $closedBy = $this->params->getRequired('closedBy');
        $client = $this->remoteClient();
        $requests = [];
        foreach ($uuids as $uuid) {
            $requests[$uuid] = $client->request('issue.close', [
                $uuid,
                $closedBy
            ]);
        }
        $result = [];
        foreach (awaitAll($requests) as $uuid => $success) {
            if ($success) {
                $result[] = $uuid;
            }
        }

        if (empty($result)) {
            $this->sendJsonResponse((object) [
                'success' => false,
                'error'   => 'Found no issue for the given ticket/issue'
            ], 201);
        } else {
            $this->sendJsonResponse((object) [
                'success'      => true,
                'closedIssues' => $result
            ]);
        }
    }

    protected function findApiRequestIssues(): array
    {
        $db = $this->db();
        if ($ticket = $this->params->get('ticket')) {
            $uuids = $db->fetchCol($db->select()->from('issue', 'issue_uuid')->where('ticket_ref = ?', $ticket));
            foreach ($uuids as $idx => $uuid) {
                $uuids[$idx] = Uuid::fromBytes($uuid)->toString();
            }
        } elseif ($uuid = $this->params->get('uuid')) {
            $uuids = [Uuid::fromString($uuid)->toString()];
        } elseif ($id = $this->params->get('sender_event_id')) {
            $uuids = $db->fetchCol($db->select()->from('issue', 'issue_uuid')->where('sender_event_id = ?', $id));
            foreach ($uuids as $idx => $uuid) {
                $uuids[$idx] = Uuid::fromBytes($uuid)->toString();
            }
        } else {
            throw new \InvalidArgumentException('Got neither "ticket" nor "uuid"');
        }

        return $uuids;
    }

    protected function closedAction()
    {
        $this->addSingleTab($this->translate('Issue'));
        $this->addTitle($this->translate('Issues closed'));
        $this->content()->add(Hint::ok(\sprintf(
            $this->translate('%d issues have been closed'),
            $this->params->getRequired('cnt')
        )));
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
}
