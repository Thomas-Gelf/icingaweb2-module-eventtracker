<?php

namespace Icinga\Module\Eventtracker\Controllers;

use gipfl\IcingaWeb2\CompatController;
use gipfl\IcingaWeb2\Url;
use gipfl\IcingaWeb2\Zf1\Db\FilterRenderer;
use gipfl\Json\JsonEncodeException;
use gipfl\Json\JsonString;
use gipfl\Web\Widget\Hint;
use gipfl\ZfDb\Select;
use Icinga\Data\Filter\Filter;
use Icinga\Data\Filter\FilterChain;
use Icinga\Data\Filter\FilterExpression;
use Icinga\Exception\NotFoundError;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

trait RestApiMethods
{
    protected function runForApi($callback)
    {
        try {
            $callback();
        } catch (NotFoundError $e) {
            $this->sendJsonError($e->getMessage(), 404);
        } catch (\Throwable $e) {
            $this->sendJsonError($e);
        }
    }

    protected function notForApi()
    {
        if ($this->getRequest()->isApiRequest()) {
            $this->sendJsonError('Not found', '404');
        }
    }

    /**
     * @param \Throwable|string $error
     * @param int $code
     */
    protected function sendJsonError($error, $code = 500)
    {
        $data = [
            'success' => false,
        ];

        if ($error instanceof \Exception) {
            $message = $error->getMessage();
            $data['trace'] = iconv('UTF-8', 'UTF-8//IGNORE', $error->getTraceAsString());
        } else {
            $message = (string) $error;
        }

        $data['error'] = iconv('UTF-8', 'UTF-8//IGNORE', $message);

        $this->sendJsonResponse($data, $code);
    }

    protected function sendJsonResponse($object, $code = 200)
    {
        /** @var $this CompatController */
        $this->getResponse()->setHttpResponseCode($code);
        $this->getResponse()->setHeader('Content-Type', 'application/json', true);
        try {
            echo JsonString::encode($object, JSON_PRETTY_PRINT) . "\n";
        } catch (JsonEncodeException $e) {
            $this->sendJsonError($e);
        }
        exit; // TODO: shutdown
    }

    protected function showApiOnly()
    {
        /** @var $this CompatController */
        $this->addSingleTab($this->translate('Error'));
        $this->addTitle($this->translate('API only'));
        $this->content()->add(Hint::error($this->translate('This URL is available for API requests only')));
    }

    protected function checkBearerToken(string $permission): bool
    {
        $token = null;
        foreach ($this->getServerRequest()->getHeader('Authorization') as $line) {
            if (preg_match('/^Bearer\s+([A-z0-9-]+)$/', $line, $match)) {
                $token = $match[1];
            }
        }
        if ($token === null) {
            $this->sendJsonError('Bearer token is required', 401);
            return false;
        }
        try {
            $uuid = Uuid::fromString($token);
        } catch (\Exception $e) {
            $this->sendJsonError($e->getMessage());
            return false;
        }
        $tokenPermissions = $this->getTokenPermissions($uuid);
        if ($tokenPermissions === null) {
            $this->sendJsonError(sprintf('Token %s is not valid', $token), 401);
        }
        if (in_array($permission, $tokenPermissions)) {
            return true;
        }

        $this->sendJsonError(sprintf('Bearer token has no %s permission', $permission), 401);
        return false;
    }

    protected function getTokenPermissions(UuidInterface $token): ?array
    {
        $db = $this->db();
        $permissions = $db->fetchOne(
            $db->select()->from('api_token', 'permissions')->where('uuid = ?', $token->getBytes())
        );
        if (empty($permissions)) {
            return null;
        }

        return JsonString::decode($permissions);
    }

    protected static function applyColumnAndFilterParams(Select $query, Url $url, array $validColumns)
    {
        $columns = self::getColumnsFromUrlParam($url->getParams()->shift('columns'), $validColumns);
        $query->columns($columns ?? '*');
        $filter = Filter::fromQueryString($url->getQueryString());
        foreach ($filter->listFilteredColumns() as $column) {
            self::assertValidColumnName($column, $validColumns);
        }
        self::tweakFilterValues($filter);
        FilterRenderer::applyToQuery($filter, $query);
    }

    protected static function tweakFilterValues(Filter $filter)
    {
        if ($filter instanceof FilterExpression) {
            if (preg_match('/_uuid$/', $filter->getColumn())) {
                $filter->setExpression(Uuid::fromString($filter->getExpression())->getBytes());
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
