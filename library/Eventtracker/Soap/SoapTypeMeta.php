<?php

namespace Icinga\Module\Eventtracker\Soap;

class SoapTypeMeta
{
    public $name;
    /** @var SoapParamMeta[] */
    public $parameters = [];

    public function __construct(string $name, array $parameters = [])
    {
        $this->name = $name;
        $this->parameters = $parameters;
    }
}
