<?php

namespace Icinga\Module\Eventtracker\Controllers;

use gipfl\IcingaWeb2\Url;
use Icinga\Application\Hook;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Eventtracker\File;
use Icinga\Module\Eventtracker\Hook\EventActionsHook;
use Icinga\Module\Eventtracker\Issue;
use Icinga\Module\Eventtracker\IssueHistory;
use Icinga\Module\Eventtracker\SetOfIssues;
use Icinga\Module\Eventtracker\Web\Form\CloseIssueForm;
use Icinga\Module\Eventtracker\Web\Form\TakeIssueForm;
use Icinga\Module\Eventtracker\Web\Widget\IdoDetails;
use Icinga\Module\Eventtracker\Web\Widget\IssueActivities;
use Icinga\Module\Eventtracker\Web\Widget\IssueDetails;
use Icinga\Module\Eventtracker\Web\Widget\IssueHeader;
use ipl\Html\Html;
use Ramsey\Uuid\Uuid;

class IssueController extends Controller
{
    public function indexAction()
    {
        $db = $this->db();
        $this->addSingleTab('Event');
        $uuid = $this->params->get('uuid');
        if ($uuid === null) {
            $issues = SetOfIssues::fromUrl($this->url(), $db);
            $count = \count($issues);
            $this->addTitle($this->translate('%d issues'), $count);
            $maxIssues = $this->Config()->get('ui', 'multiselect_max_issues', 50);
            if ($count > $maxIssues) {
                $this->content()->add(Html::tag('p', [
                    'class' => 'state-hint warning'
                ], \sprintf($this->translate('Please select no more than %s issues'), $maxIssues)));

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

            /** @var EventActionsHook $impl */
            foreach (Hook::all('eventtracker/EventActions') as $impl) {
                $this->actions()->add($impl->getIssuesActions($issues));
            }
            foreach ($issues->getIssues() as $issue) {
                $this->content()->add($this->issueHeader($issue));
            }
        } else {
            $binaryUuid = Uuid::fromString($uuid)->getBytes();
            if ($issue = Issue::loadIfExists($binaryUuid, $db)) {
                $this->showIssue($issue);
            } elseif (IssueHistory::exists($binaryUuid, $db)) {
                $this->addTitle($this->translate('Issue has been closed'));
                $this->content()->add(Html::tag('p', [
                    'class' => 'state-hint ok'
                ], $this->translate('This issue has been closed.')));
                $issue = Issue::loadFromHistory($binaryUuid, $db);
                $this->showIssue($issue);
            } else {
                $this->addTitle($this->translate('Not found'));
                $this->content()->add(Html::tag('p', [
                    'class' => 'state-hint error'
                ], $this->translate('There is no such issue')));
            }
        }
    }

    public function fileAction()
    {
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

    protected function showIssue(Issue $issue)
    {
        $db = $this->db();
        if ($hostname = $issue->get('host_name')) {
            $this->addTitle(sprintf(
                '%s (%s)',
                $issue->get('object_name'),
                $issue->get('host_name')
            ));
        } else {
            $this->addTitle($issue->get('object_name'));
        }
        // $this->addHookedActions($issue);
        $this->content()->add([
            $this->issueHeader($issue),
            new IdoDetails($issue, $db),
            new IssueDetails($issue),
            new IssueActivities($issue, $db),
        ]);
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

    protected function closedAction()
    {
        $this->addSingleTab($this->translate('Event'));
        $this->addTitle($this->translate('Issues closed'));
        $this->content()->add(
            Html::tag('p', [
                'class' => 'state-hint ok'
            ], \sprintf(
                $this->translate('%d issues have been closed'),
                $this->params->getRequired('cnt')
            ))
        );
    }

    protected function issueHeader(Issue $issue)
    {
        return new IssueHeader($issue, $this->db(), $this->getServerRequest(), $this->getResponse(), $this->Auth());
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
