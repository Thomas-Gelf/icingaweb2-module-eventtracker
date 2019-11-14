<?php

namespace Icinga\Module\Eventtracker\Daemon;

use Zend_Db_Adapter_Abstract as ZfDb;

class IcingaCiSync
{
    const TBL_CI = 'icinga_ci';
    const TBL_VAR = 'icinga_ci_var';

    /** @var ZfDb */
    protected $db;

    /** @var IdoDb */
    protected $idoDb;

    /** @var string */
    protected $lastHostsChecksum;

    /** @var array */
    protected $customVarNames = [];

    public function __construct(ZfDb $db, IdoDb $idoDb)
    {
        $this->db = $db;
        $this->idoDb = $idoDb;
    }

    public function setCustomVarNames(array $varNames)
    {
        $this->customVarNames = $varNames;

        return $this;
    }

    public function sync()
    {
        $hosts = $this->idoDb->fetchHostsWithVars($this->customVarNames);
        $checksum = $this->idoDb->getLastHostsChecksum();
        if ($checksum !== $this->lastHostsChecksum) {
            $this->replaceHosts($hosts);
            $this->lastHostsChecksum = $checksum;
        }
    }

    /**
     * @param $idoHosts
     * @throws \Exception
     */
    protected function replaceHosts($idoHosts)
    {
        $current = $this->fetchHosts();
        $create = [];
        $delete = [];
        $modify = [];
        foreach ($idoHosts as $id => $host) {
            if (isset($current[$id])) {
                $currentHost = $current[$id];
                if ($currentHost->checksum === $host->checksum) {
                    continue;
                } else {
                    $modify[$id] = $host;
                }
            } else {
                $create[$id] = $host;
            }
        }

        foreach ($current as $id => $host) {
            if (! isset($idoHosts[$id])) {
                $delete[$id] = $host;
            }
        }

        $this->db->beginTransaction();
        try {
            $this->deleteIds($delete);
            foreach ($create as $ci) {
                $this->createCi($ci);
            }
            foreach ($modify as $ci) {
                $this->updateCi($current[$ci->object_id], $ci);
            }
            $this->db->commit();
        } catch (\Exception $e) {
            try {
                Logger::error($e->getMessage());
                $this->db->rollBack();
            } catch (\Exception $rollbackError) {
                Logger::error($rollbackError->getMessage());
            }

            throw $e;
        }
        if (\count($create) + \count($modify) + \count($delete) > 0) {
            Logger::info(\sprintf(
                'IDO sync: %d created, %d modified, %d deleted',
                \count($create),
                \count($modify),
                \count($delete)
            ));
        } else {
            // Logger::debug('IDO sync: nothing has been changed');
        }
    }

    /**
     * @param $ci
     * @throws \Zend_Db_Adapter_Exception
     */
    protected function createCi($ci)
    {
        $db = $this->db;
        $vars = $ci->vars;
        unset($ci->vars);
        $db->insert(self::TBL_CI, (array) $ci);
        foreach ($vars as $varName => $varValue) {
            if (\is_string($varValue)) {
                $format = 'string';
            } else {
                $varValue = \json_encode($varValue);
                $format = 'json';
            }
            $db->insert(self::TBL_VAR, [
                'object_id' => $ci->object_id,
                'varname'   => $varName,
                'varvalue'  => $varValue,
                'varformat' => $format,
            ]);
        }
    }

    /**
     * @param $current
     * @param $new
     * @throws \Zend_Db_Adapter_Exception
     */
    protected function updateCi($current, $new)
    {
        $db = $this->db;
        $newVars = $new->vars;
        unset($new->vars);
        $this->db->update(
            self::TBL_CI,
            (array) $new, // TODO: only update changed properties
            $db->quoteInto('object_id = ?', $current->object_id)
        );

        $vars = $current->vars;
        $create = [];
        $delete = [];
        $modify = [];
        foreach ($newVars as $varName => $varValue) {
            if (\property_exists($vars, $varName)) {
                if ($vars->$varName !== $varValue) {
                    $modify[$varName] = $varValue;
                }
            } else {
                $create[$varName] = $varValue;
            }
        }
        foreach (array_keys((array) $vars) as $varName) {
            if (! property_exists($newVars, $varName)) {
                $delete[] = $varName;
            }
        }

        $objectId = $current->object_id;
        $this->deleteVars($objectId, $delete);
        $this->createVars($objectId, $create);
        $this->modifyVars($objectId, $modify);
    }

    /**
     * @param $objectId
     * @param $vars
     * @return int
     * @throws \Zend_Db_Adapter_Exception
     */
    protected function modifyVars($objectId, $vars)
    {
        $db = $this->db;
        $modified = 0;
        foreach ($vars as $varName => $varValue) {
            if (\is_string($varValue)) {
                $format = 'string';
            } else {
                $varValue = \json_encode($varValue);
                $format = 'json';
            }
            $where = $this->db->quoteInto('object_id = ?', $objectId)
                . ' AND '
                . $this->db->quoteInto('varname = ?', $varName);

            $modified += $db->update(self::TBL_VAR, [
                'varvalue'  => $varValue,
                'varformat' => $format,
            ], $where);
        }

        return $modified;
    }

    /**
     * @param $objectId
     * @param $vars
     * @throws \Zend_Db_Adapter_Exception
     */
    protected function createVars($objectId, $vars)
    {
        $db = $this->db;
        foreach ($vars as $varName => $varValue) {
            if (\is_string($varValue)) {
                $format = 'string';
            } else {
                $varValue = \json_encode($varValue);
                $format = 'json';
            }
            $db->insert(self::TBL_VAR, [
                'object_id' => $objectId,
                'varname'   => $varName,
                'varvalue'  => $varValue,
                'varformat' => $format,
            ]);
        }
    }

    protected function deleteVars($objectId, $varNames)
    {
        if (empty($varNames)) {
            return 0;
        }

        return $this->db->delete(
            self::TBL_VAR,
            $this->db->quoteInto('object_id = ?', $objectId)
            . ' AND '
            . $this->db->quoteInto('varname IN (?)', $varNames)
        );
    }

    protected function deleteIds($ids)
    {
        $deleted = 0;
        if (empty($ids)) {
            return $deleted;
        }

        foreach (array_chunk($ids, 1000)  as $chunk) {
            $deleted += $this->db->delete(
                self::TBL_CI,
                $this->db->quoteInto('object_id IN (?)', $chunk)
            );
        }

        return $deleted;
    }

    protected function fetchHosts()
    {
        $hosts = [];
        foreach ($this->db->fetchAll($this->selectHosts()) as $row) {
            $row->vars = (object) [];
            $hosts[$row->object_id] = $row;
        }

        foreach ($this->fetchAllHostVars() as $row) {
            $host = $hosts[$row->object_id];
            // TODO: json_decode?
            if ($row->varformat === 'json') {
                $value = \json_decode($row->varvalue);
            } else {
                $value = $row->varvalue;
            }
            $host->vars->{$row->varname} = $value;
        }

        return $hosts;
    }

    protected function selectHosts()
    {
        return  $this->db->select()
            ->from(['ci' => self::TBL_CI], [
                'object_id',
                'host_id',
                'object_type',
                'checksum',
                'host_name',
                'service_name',
                'display_name',
            ])->where('object_type = ?', 'host');
    }

    protected function fetchAllHostVars()
    {
        return $this->fetchAllObjectTypeVars('host');
    }

    public function fetchAllObjectTypeVars($objectType)
    {
        $query = $this->db->select()
            ->from(['civ' => self::TBL_VAR], [
                'civ.object_id',
                'civ.varname',
                'civ.varvalue',
                'civ.varformat',
            ])
            ->join(
                ['ci' => self::TBL_CI],
                'ci.object_id = civ.object_id',
                []
            )
            ->where('ci.object_type = ?', $objectType)
            // ->order('ci.object_type') // only required when not filtering by type
            ->order('ci.host_name')
            ->order('ci.service_name')
            ->order('civ.varname');

        return $this->db->fetchAll($query);
    }
}
