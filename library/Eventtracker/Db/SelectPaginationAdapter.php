<?php

namespace Icinga\Module\Eventtracker\Db;

use gipfl\IcingaWeb2\Data\Paginatable;
use gipfl\ZfDb\Select;
use gipfl\ZfDb\Exception\SelectException;
use Icinga\Application\Benchmark;
use Icinga\Data\SimpleQuery;
use RuntimeException;
use Zend_Db_Select as ZfSelect;
use Zend_Db_Select_Exception as ZfDbSelectException;

class SelectPaginationAdapter implements Paginatable
{
    private $query;

    private $countQuery;

    private $cachedCount;

    private $cachedCountQuery;

    public function __construct($query)
    {
        if ($query instanceof Select || $query instanceof ZfSelect || $query instanceof SimpleQuery) {
            $this->query = $query;
        } else {
            throw new RuntimeException('Got no supported ZF1 Select object');
        }
    }

    public function getCountQuery()
    {
        if ($this->countQuery === null) {
            $this->countQuery = (new CountQuery($this->query))->getQuery();
        }

        return $this->countQuery;
    }

    #[\ReturnTypeWillChange]
    public function count()
    {
        $countQuery = $this->getCountQuery();
        if ($countQuery instanceof SimpleQuery) {
            return current((array) $countQuery->fetchRow());
        }
        $queryString = (string) $countQuery;
        if ($this->cachedCountQuery !== $queryString) {
            Benchmark::measure('Running count() for pagination');
            $this->cachedCountQuery = $queryString;
            $count = $this->query->getAdapter()->fetchOne(
                $queryString
            );
            $this->cachedCount = $count;
            Benchmark::measure("Counted $count rows");
        }

        return $this->cachedCount;
    }

    public function limit($count = null, $offset = null)
    {
        $this->query->limit($count, $offset);
    }

    public function hasLimit()
    {
        return $this->getLimit() !== null;
    }

    public function getLimit()
    {
        return $this->getQueryPart(Select::LIMIT_COUNT);
    }

    public function setLimit($limit)
    {
        $this->query->limit(
            $limit,
            $this->getOffset()
        );
    }

    public function hasOffset()
    {
        return $this->getOffset() !== null;
    }

    public function getOffset()
    {
        return $this->getQueryPart(Select::LIMIT_OFFSET);
    }

    protected function getQueryPart($part)
    {
        if ($this->query instanceof SimpleQuery) {
            switch ($part) {
                case Select::LIMIT_COUNT:
                    return $this->query->getLimit();
                case Select::LIMIT_OFFSET:
                    return $this->query->getOffset();
                default:
                    die('dd');
            }
        }
        try {
            return $this->query->getPart($part);
        } catch (SelectException $e) {
            // Will not happen if $part is correct.
            throw new RuntimeException($e);
        } catch (ZfDbSelectException $e) {
            // Will not happen if $part is correct.
            throw new RuntimeException($e);
        }
    }

    public function setOffset($offset)
    {
        $this->query->limit(
            $this->getLimit(),
            $offset
        );
    }
}
