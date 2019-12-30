<?php

namespace Icinga\Module\Eventtracker\Hook;

use Icinga\Module\Eventtracker\Issue;
use Zend_Db_Adapter_Abstract as Db;

abstract class IssueHook
{
    /** @var Db */
    protected $db;

    public function setDb(Db $db)
    {
        $this->db = $db;
        return $this;
    }

    /**
     * @return Db
     */
    protected function getDb()
    {
        return $this->db;
    }

    public function onCreate(Issue $issue)
    {
    }

    public function onClose(Issue $issue)
    {
    }

    public function onUpdate(Issue $issue)
    {
    }

    public function onReOpen(Issue $issue)
    {
    }

    public function onDowntime(Issue $issue)
    {
    }

    public function onAcknowledge(Issue $issue)
    {
    }
}
