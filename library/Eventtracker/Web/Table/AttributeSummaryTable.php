<?php

namespace Icinga\Module\Eventtracker\Web\Table;

use gipfl\IcingaWeb2\Url;
use gipfl\Json\JsonString;
use gipfl\ZfDb\Expr;

class AttributeSummaryTable extends BaseSummaryTable
{
    protected $searchColumns = [
        's.sender_name',
        's.implementation',
    ];
    protected string $attributeName;
    protected string $attributeTitle;

    public function __construct(string $attributeName, string $attributeTitle, $db, ?Url $url = null)
    {
        $this->attributeName = $attributeName;
        $this->attributeTitle = $attributeTitle;
        parent::__construct($db, $url);
    }

    protected function getMainColumn(): Expr
    {
        return new Expr("JSON_EXTRACT(attributes, '$." . $this->attributeName . "')");
    }

    protected function getMainColumnAlias(): string
    {
        return 'attr';
    }

    protected function getMainColumnTitle(): string
    {
        return $this->attributeTitle;
    }

    protected function getFilterParamName(): string
    {
        return 'attributes.' . $this->attributeName;
    }

    protected function decodeRowValue(?string $value): ?string
    {
        return JsonString::decodeOptional($value);
    }
}
