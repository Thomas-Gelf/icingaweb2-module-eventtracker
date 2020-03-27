<?php

namespace Icinga\Module\Eventtracker\Controllers;

use gipfl\IcingaWeb2\CompatController;
use Icinga\Authentication\Auth;
use Icinga\Data\Db\DbConnection;
use Icinga\Module\Eventtracker\Uuid;
use Icinga\Module\Eventtracker\Web\Table\BaseTable;
use Icinga\Module\Eventtracker\Web\Widget\AdditionalTableActions;

abstract class Controller extends CompatController
{
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
        $this->eventuallySendJson($table);
        $table->renderTo($this);
    }

    /**
     * @return DbConnection
     */
    protected function getScomDb()
    {
        return DbConnection::fromResourceName(
            $this->Config()->get('scom', 'db_resource')
        );
    }

    protected function eventuallySendJson(BaseTable $table)
    {
        if ($this->getRequest()->isApiRequest() || $this->getParam('format') === 'json') {
            $table->ensureAssembled();
            $result = $table->fetch();
            foreach ($result as & $row) {
                // For some tables only
                if (isset($row->issue_uuid)) {
                    $row->issue_uuid = Uuid::toHex($row->issue_uuid);
                }
            }
            $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
            $this->getResponse()->setHeader('Content-Type', 'application/json', true)->sendHeaders();
            echo json_encode($result, $flags);
            exit;
        }
    }
}
