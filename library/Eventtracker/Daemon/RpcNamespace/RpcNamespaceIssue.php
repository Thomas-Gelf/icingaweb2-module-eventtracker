<?php

namespace Icinga\Module\Eventtracker\Daemon\RpcNamespace;

use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;
use gipfl\ZfDb\Adapter\Adapter as Db;
use Icinga\Module\Eventtracker\Daemon\DbBasedComponent;
use Icinga\Module\Eventtracker\Issue;
use Icinga\Module\Eventtracker\IssueHistory;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use React\Promise\ExtendedPromiseInterface;
use RuntimeException;

use function React\Promise\resolve;

class RpcNamespaceIssue implements DbBasedComponent, EventEmitterInterface
{
    use EventEmitterTrait;

    protected ?Db $db = null;
    protected LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param Issue $issue
     * @return bool
     */
    public function recoverRequest(Issue $issue): bool
    {
        if ($this->db === null) {
            throw new RuntimeException('Cannot recover the given issue, I have no DB connection');
        }

        return Issue::closeIssue($issue, $this->db, IssueHistory::REASON_RECOVERY);
    }

    /**
     * @param Issue $issue
     * @return bool
     */
    public function expireRequest(Issue $issue): bool
    {
        if ($this->db === null) {
            throw new RuntimeException('Cannot expire the given issue, I have no DB connection');
        }

        return Issue::closeIssue($issue, $this->db, IssueHistory::REASON_EXPIRATION);
    }

    /**
     * @param string $issueUuid
     * @param string $closedBy
     * @return bool
     */
    public function closeRequest(string $issueUuid, ?string $closedBy = null): bool
    {
        if ($this->db === null) {
            throw new RuntimeException('Cannot close the given issue, I have no DB connection');
        }
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

    /**
     * @param string $issueUuid
     * @param string $owner
     * @param string $ticketReference
     * @return bool
     */
    protected function acknowledge(UuidInterface $uuid, string $owner, ?string $ticketReference = null): bool
    {
        if ($this->db === null) {
            throw new RuntimeException('Cannot close the given issue, I have no DB connection');
        }
        $db = $this->db;
        $issue = Issue::load($uuid->getBytes(), $db);
        $issue->set('status', 'acknowledged');
        $issue->set('owner', $owner);
        if ($ticketReference !== null) {
            $issue->set('ticket_ref', $ticketReference);
        }
        $issue->storeToDb($db);

        return true;
    }

    public function initDb(Db $db): ExtendedPromiseInterface
    {
        $this->db = $db;

        return resolve(null);
    }

    public function stopDb(): ExtendedPromiseInterface
    {
        $this->db = null;

        return resolve(null);
    }
}
