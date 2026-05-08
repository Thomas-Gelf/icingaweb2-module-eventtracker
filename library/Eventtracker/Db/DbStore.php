<?php

namespace Icinga\Module\Eventtracker\Db;

use gipfl\ZfDbStore\DbStorable;
use gipfl\ZfDbStore\DbStorableInterface;
use gipfl\ZfDbStore\NotFoundError;
use gipfl\ZfDbStore\StorableInterface;
use gipfl\ZfDbStore\ZfDbStore;
use InvalidArgumentException;
use RuntimeException;

class DbStore extends ZfDbStore
{
    public function enum(StorableInterface $storable, $keyColumn = null, $labelColumn = null)
    {
        assert($storable instanceof DbStorableInterface);
        if ($keyColumn === null) {
            $key = $storable->getKeyProperty();
            if (is_array($key)) {
                if ($storable->hasAutoIncKey()) {
                    $key = $storable->getAutoIncKeyName();
                } else {
                    throw new InvalidArgumentException(
                        'Cannot provide an enum for a multi-key column'
                    );
                }
            }
        } else {
            $key = $keyColumn;
        }

        if ($labelColumn === null) {
            if (method_exists($storable, 'getDisplayColumn')) {
                $label = $storable->getDisplayColumn();
            } else {
                $label = $storable->getKeyProperty();
                if (is_array($label)) {
                    $label = $key;
                }
            }
        } else {
            $label = $labelColumn;
        }

        $columns = [
            'key_col'   => $key,
            'label_col' => $label
        ];

        $query = $this->db->select()->from(
            $this->getTableName($storable),
            $columns
        );

        // return $this->db->fetchPairs($query);

        $result = [];
        foreach ($this->db->fetchAll($query) as $row) {
            $result[$row->key_col] = $row->label_col;
        }

        return $result;
    }

    protected function insertIntoStore(StorableInterface $storable)
    {
        assert($storable instanceof DbStorableInterface);
        $result = $this->db->insert(
            $this->getTableName($storable),
            $this->preparePropertiesForDb($storable->getProperties())
        );
        /** @var DbStorable $storable */
        if ($storable->hasAutoIncKey()) {
            $storable->set(
                $storable->getAutoIncKeyName(),
                $this->db->lastInsertId($this->getTableName($storable))
            );
        }

        return $result > 0;
    }

    protected function updateStore(StorableInterface $storable)
    {
        assert($storable instanceof DbStorableInterface);
        $this->db->update(
            $this->getTableName($storable),
            $storable->getProperties(),
            $this->createWhere($storable)
        );

        return true;
    }

    protected static function fixResultProperties(array $properties): array
    {
        foreach ($properties as &$value) {
            if (is_resource($value)) {
                $value = stream_get_contents($value);
            }
        }

        return $properties;
    }

    protected function preparePropertiesForDb(array $properties): array
    {
        foreach ($properties as $key => &$value) {
            if ($key === 'uuid' || preg_match('/_uuid$/', $key)) {
                $value = DbUtil::quoteBinary($value, $this->db);
            }
        }

        return $properties;
    }

    protected function loadFromStore(StorableInterface $storable, $key)
    {
        assert($storable instanceof DbStorableInterface);
        $keyColumn = $storable->getKeyProperty();
        $select = $this->db->select()->from($this->getTableName($storable));

        if (is_string($keyColumn)) {
            $select->where("$keyColumn = ?", $key);
        } else {
            foreach ($keyColumn as $column) {
                if (array_key_exists($column, $key)) {
                    $select->where("$column = ?", $key[$column]);
                } else {
                    throw new RuntimeException(sprintf('Multicolumn key required, got no %s', $column));
                }
            }
        }

        $result = $this->db->fetchAll($select);
        // TODO: properties should be changed in storeProperties
        // when you load the element from db before changing it.
        if (empty($result)) {
            throw new NotFoundError('Not found: ' . $this->describeKey($storable, $key));
        }

        if (count($result) > 1) {
            throw new NotFoundError(sprintf(
                'One row expected, got %s: %s',
                count($result),
                $this->describeKey($storable, $key)
            ));
        }

        $storable->setStoredProperties(self::fixResultProperties((array) $result[0]));

        return $storable;
    }

    protected function createWhere($storable)
    {
        $where = [];
        foreach ((array) $storable->getKeyProperty() as $key) {
            $value = $storable->get($key);
            // TODO, eventually:
            // $key = $this->db->quoteIdentifier($key);
            if ($value === null) {
                $where[] = "$key IS NULL";
            } else {
                if ($key === 'uuid' || preg_match('/_uuid$/', $key)) {
                    $value = DbUtil::quoteBinary($value, $this->db);
                }
                $where[] = $this->db->quoteInto("$key = ?", $value);
            }
        }

        return implode(' AND ', $where);
    }
}
