<?php

namespace Icinga\Module\Eventtracker\Filter;

use gipfl\IcingaWeb2\Url;
use gipfl\IcingaWeb2\Zf1\Db\FilterRenderer;
use gipfl\ZfDb\Expr;
use gipfl\ZfDb\Select;
use Icinga\Data\Filter\Filter;
use Icinga\Data\Filter\FilterChain;
use Icinga\Data\Filter\FilterExpression;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;

class FilterHelper
{
    public static function extractOptionalAttributesColumn(string $column): ?string
    {
        if (preg_match('/^attributes\.([a-zA-Z_]+)/', $column, $match)) {
            return $match[1];
        }

        return null;
    }

    public static function applyColumnAndFilterParams(Select $query, Url $url, array $validColumns)
    {
        $columns = self::getColumnsFromUrlParam($url->getParams()->shift('properties'), $validColumns);
        $query->columns($columns ?? '*');
        $filter = Filter::fromQueryString($url->getQueryString());
        foreach ($filter->listFilteredColumns() as $column) {
            if ($attributeColumn = self::extractOptionalAttributesColumn($column)) {
                $query->columns([
                    'attributes_' . $attributeColumn => new Expr(
                        "JSON_EXTRACT(attributes, '$." . $attributeColumn . "')"
                    )
                ]);
            } else {
                self::assertValidColumnName($column, $validColumns);
            }
        }
        self::tweakFilterValues($filter);
        FilterRenderer::applyToQuery($filter, $query);
    }

    protected static function tweakFilterValues(Filter $filter)
    {
        if ($filter instanceof FilterExpression) {
            if (preg_match('/_uuid$/', $filter->getColumn())) {
                $filter->setExpression(Uuid::fromString($filter->getExpression())->getBytes());
            } elseif ($attributeColumn = self::extractOptionalAttributesColumn($filter->getColumn())) {
                $filter->setColumn('attributes_' . $attributeColumn);
            }
        } elseif ($filter instanceof FilterChain) {
            foreach ($filter->filters() as $subFilter) {
                self::tweakFilterValues($subFilter);
            }
        }
    }

    protected static function getColumnsFromUrlParam(?string $param, ?array $validColumns): ?array
    {
        if ($param === null) {
            return null;
        }

        $columns = preg_split('/\s*,\s*/', $param);
        if ($columns) {
            foreach ($columns as $column) {
                self::assertValidColumnName($column, $validColumns);
            }
        } else {
            return null;
        }

        return $columns;
    }

    protected static function assertValidColumnName(string $column, ?array $validColumns)
    {
        if ($validColumns) {
            if (!in_array($column, $validColumns)) {
                throw new InvalidArgumentException("'$column is not a valid column name");
            }
        }

        if (! preg_match('/^[a-z][a-z0-9_]*[a-z0-9]/', $column)) {
            throw new InvalidArgumentException("'$column is not a valid column name");
        }
    }
}
