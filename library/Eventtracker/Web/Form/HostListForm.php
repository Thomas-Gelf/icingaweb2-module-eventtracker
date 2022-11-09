<?php

namespace Icinga\Module\Eventtracker\Web\Form;

use Ramsey\Uuid\UuidInterface;

class HostListForm extends UuidObjectForm
{
    protected $table = 'host_list';
    protected $membersTable = 'host_list_member';

    protected function assemble()
    {
        $this->addElement('text', 'label', [
            'label'    => $this->translate('Label'),
            'required' => true,
        ]);
        $this->addElement('textarea', 'hosts', [
            'label'  => $this->translate('Hosts'),
            'value'  => $this->hasBeenSent() ? null : implode("\n", $this->loadHosts()),
            'rows'   => 10,
            'ignore' => true,
        ]);
        $this->addButtons();
    }

    protected function loadHosts(): array
    {
        if ($this->uuid === null) {
            return [];
        }
        $db = $this->store->getDb();
        return $db->fetchCol(
            $db->select()->from(['hlm' => $this->membersTable], 'hostname')
                ->where('list_uuid = ?', $this->uuid->getBytes())
        );
    }

    protected function storeObject()
    {
        $db = $this->store->getDb();
        $result = parent::storeObject();
        $hosts = preg_split('/\s*\r?\n\s*/', $this->getValue('hosts'), -1, PREG_SPLIT_NO_EMPTY);
        $hosts = array_unique(array_map('strtolower', $hosts));
        $hosts = array_combine($hosts, $hosts);
        $delete = [];
        if ($result instanceof UuidInterface) {
            $binaryUuid = $result->getBytes();
            $add = $hosts;
        } else {
            $binaryUuid = $this->uuid->getBytes();
            $add = [];
            $current = $this->loadHosts();
            $current = array_combine($current, $current);
            foreach ($current as $host) {
                if (! isset($hosts[$host])) {
                    $delete[] = $host;
                }
            }
            foreach ($hosts as $host) {
                if (! isset($current[$host])) {
                    $add[] = $host;
                }
            }
        }

        foreach ($add as $host) {
            $db->insert($this->membersTable, [
                'list_uuid' => $binaryUuid,
                'hostname' => $host,
            ]);
        }
        $whereUuid = $db->quoteInto('list_uuid = ?', $binaryUuid);
        foreach ($delete as $host) {
            $where = $whereUuid . $db->quoteInto(' AND hostname = ?', $host);
            $db->delete($this->membersTable, $where);
        }

        if ($result === false && count($add) + count($delete) > 0) {
            $result = true;
        }

        return $result;
    }
}
