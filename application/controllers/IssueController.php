<?php

namespace Icinga\Module\Eventtracker\Controllers;

use gipfl\IcingaWeb2\CompatController;
use Icinga\Module\Eventtracker\DbFactory;
use Icinga\Module\Eventtracker\Hook\EventActionsHook;
use Icinga\Module\Eventtracker\Issue;
use Icinga\Module\Eventtracker\SetOfIssues;
use Icinga\Module\Eventtracker\Uuid;
use Icinga\Module\Eventtracker\Web\Widget\IssueActivities;
use Icinga\Module\Eventtracker\Web\Widget\IssueDetails;
use Icinga\Module\Eventtracker\Web\Widget\IssueHeader;
use Icinga\Web\Hook;

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
            $issue = $this->loadIssue($uuid, $db);

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
    }

    protected function issueHeader(Issue $issue)
    {
        return new IssueHeader($issue, $this->getServerRequest(), $this->getResponse(), $this->Auth());
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
