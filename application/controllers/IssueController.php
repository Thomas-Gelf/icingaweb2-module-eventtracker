<?php

namespace Icinga\Module\Eventtracker\Controllers;

use gipfl\IcingaWeb2\CompatController;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Eventtracker\DbFactory;
use Icinga\Module\Eventtracker\Hook\EventActionsHook;
use Icinga\Module\Eventtracker\Issue;
use Icinga\Module\Eventtracker\IssueHistory;
use Icinga\Module\Eventtracker\SetOfIssues;
use Icinga\Module\Eventtracker\Uuid;
use Icinga\Module\Eventtracker\Web\Widget\IssueActivities;
use Icinga\Module\Eventtracker\Web\Widget\IssueDetails;
use Icinga\Module\Eventtracker\Web\Widget\IssueHeader;
use Icinga\Web\Hook;
use ipl\Html\Html;

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
                $this->content()->add($this->issueHeader($issue));
            }
        } else {
            $uuid = Uuid::toBinary($uuid);
            if ($issue = Issue::loadIfExists($uuid, $db)) {
                $this->showIssue($issue);
            } elseif (IssueHistory::exists($uuid, $db)) {
                $this->addTitle($this->translate('Issue has been closed'));
                $this->content()->add(Html::tag('p', [
                    'class' => 'state-hint ok'
                ], $this->translate('This issue has already been closed.')
                    . ' '
                    . $this->translate('Future versions will show an Issue history in this place')));
            } else {
                $this->addTitle($this->translate('Not found'));
                $this->content()->add(Html::tag('p', [
                    'class' => 'state-hint error'
                ], 'There is no such issue'));
            }
        }
    }

    protected function showIssue(Issue $issue)
    {
        $db = DbFactory::db();
        $this->addTitle(sprintf(
            '%s (%s)',
            $issue->get('object_name'),
            $issue->get('host_name')
        ));
        // $this->addHookedActions($issue);
        $this->content()->add([
            $this->issueHeader($issue),
            new IssueActivities($issue, $db),
            new IssueDetails($issue)
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

    protected function issueHeader(Issue $issue)
    {
        return new IssueHeader($issue, $this->getServerRequest(), $this->getResponse(), $this->Auth());
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
