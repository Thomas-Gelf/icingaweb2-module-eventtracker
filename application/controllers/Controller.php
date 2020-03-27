<?php

namespace Icinga\Module\Eventtracker\Controllers;

use gipfl\IcingaWeb2\CompatController;
use Icinga\Module\Eventtracker\Uuid;
use Icinga\Module\Eventtracker\Web\Table\BaseTable;

class Controller extends CompatController
{
    protected function showCompact()
    {
        return $this->params->get('view') === 'compact';
    }

    protected function eventuallySendJson(BaseTable $table)
    {
        if ($this->getRequest()->isApiRequest() || $this->getParam('format') === 'json') {
            $table->ensureAssembled();
            $result = $table->fetch();
            foreach ($result as & $row) {
                $row->issue_uuid = Uuid::toHex($row->issue_uuid);
            }
            $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
            $this->getResponse()->setHeader('Content-Type', 'application/json', true)->sendHeaders();
            echo json_encode($result, $flags);
            exit;
        }
    }
}
