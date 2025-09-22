<?php

namespace Icinga\Module\Eventtracker\Soap;

class SoapMethodMeta
{
    /** @var string */
    public $name;
    /** @var SoapParamMeta[] */
    public $parameters = [];
    /** @var ?string */
    public $returnType = null;

    public function __construct(string $name, array $parameters = [], ?string $returnType = null)
    {
        $this->name = $name;
        $this->parameters = $parameters;
        $this->returnType = $returnType;
    }
}
