<?php

namespace Icinga\Module\Eventtracker\Controllers;

use gipfl\IcingaWeb2\CompatController;
use gipfl\Json\JsonString;
use gipfl\ZfDb\Adapter\Adapter;
use gipfl\ZfDb\Adapter\Adapter as Db;
use gipfl\ZfDb\Adapter\Pdo\Mssql;
use Icinga\Authentication\Auth;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Eventtracker\Config\IcingaResource;
use Icinga\Module\Eventtracker\Db\ZfDbConnectionFactory;
use Icinga\Module\Eventtracker\DbFactory;
use Icinga\Module\Eventtracker\Web\Table\BaseTable;
use Icinga\Module\Eventtracker\Web\Widget\AdditionalTableActions;
use Ramsey\Uuid\Uuid;

abstract class Controller extends CompatController
{
    /** @var Db */
    private $db;

    /**
     * @throws NotFoundError
     */
    public function init()
    {
        if (! $this->getRequest()->isApiRequest()
            && $this->Config()->get('ui', 'disabled', 'no') === 'yes'
        ) {
            throw new NotFoundError('Not found');
        }
    }

    /**
     * @return Db
     */
    protected function db()
    {
        if ($this->db === null) {
            $this->db = DbFactory::db();
        }

        return $this->db;
    }

    protected function showCompact()
    {
        return $this->params->get('view') === 'compact';
    }

    protected function addTable(BaseTable $table, $defaultSort, $defaultLimit = 25)
    {
        if (! $this->url()->getParam('sort')) {
            // This is required to trigger sort detection
            $this->url()->setParam('sort', $defaultSort);
        }

        (new AdditionalTableActions($table, Auth::getInstance(), $this->url()))
            ->appendTo($this->actions());

        $table->getQuery()->limit($defaultLimit);
        $this->optionallySendJsonForTable($table);
        $table->renderTo($this);
    }

    /**
     * @return Adapter|Mssql
     */
    protected function getScomDb()
    {
        return ZfDbConnectionFactory::connection(
            IcingaResource::requireResourceConfig($this->Config()->get('scom', 'db_resource'))
        );
    }

    protected function optionallySendJsonForTable(BaseTable $table)
    {
        if ($this->getRequest()->isApiRequest() || $this->getParam('format') === 'json') {
            $table->ensureAssembled();
            $result = $table->fetch();
            foreach ($result as $row) {
                // For some tables only
                if (isset($row->issue_uuid)) {
                    $row->issue_uuid = Uuid::fromBytes($row->issue_uuid)->toString();
                }
            }
            $this->getResponse()->setHeader('Content-Type', 'application/json', true)->sendHeaders();
            echo JsonString::encode($result, JSON_PRETTY_PRINT);
            exit;
        }
    }
}
