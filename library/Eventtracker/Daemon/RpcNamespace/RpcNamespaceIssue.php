<?php

namespace Icinga\Module\Eventtracker\Daemon\RpcNamespace;

use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;
use gipfl\ZfDb\Adapter\Adapter as Db;
use Icinga\Module\Eventtracker\Issue;
use Icinga\Module\Eventtracker\IssueHistory;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class RpcNamespaceIssue implements EventEmitterInterface
{
    use EventEmitterTrait;

    protected Db $db;
    protected LoggerInterface $logger;

    public function __construct(Db $db, LoggerInterface $logger)
    {
        $this->db = $db;
        $this->logger = $logger;
    }

    /**
     * @param Issue $issue
     * @return bool
     */
    public function recoverRequest(Issue $issue): bool
    {
        return Issue::closeIssue($issue, $this->db, IssueHistory::REASON_RECOVERY);
    }

    /**
     * @param Issue $issue
     * @return bool
     */
    public function expireRequest(Issue $issue): bool
    {
        return Issue::closeIssue($issue, $this->db, IssueHistory::REASON_EXPIRATION);
    }

    /**
     * @param string $issueUuid
     * @param string $closedBy
     * @return bool
     */
    public function closeRequest(string $issueUuid, ?string $closedBy = null): bool
    {
        $uuid = Uuid::fromString($issueUuid);
        $issue = Issue::loadIfExists($uuid->getBytes(), $this->db);
        return $issue && Issue::closeIssue($issue, $this->db, IssueHistory::REASON_MANUAL, $closedBy);
    }

    protected function close(UuidInterface $uuid, $reason, $closedBy = null): bool
    {
        // This reflects Issue::closeIssue, and should replace that code. Currently missing: hooks!

        $db = $this->db;
        // TODO: Update? Log warning? Merge actions?
        //       -> This happens only when closing the issue formerly failed badly
        $issue = Issue::load($uuid->getBytes(), $db);
        if (! IssueHistory::exists($uuid->getBytes(), $this->db)) {
            IssueHistory::persist($issue, $db, $reason, $closedBy);
            $issue->set('status', 'closed');
            /*
             NOT YET
            $action = $issue->detectEventualHookAction();
            if ($action !== null) {
                $issue->triggerHooks($action, $db);
            }
            */
        }

        $db->delete(Issue::TABLE_NAME, $db->quoteInto('issue_uuid = ?', $issue->getUuid()));

        return true;
    }
}
