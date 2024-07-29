<?php

namespace Icinga\Module\Eventtracker\Soap;

/**
 * TODO: this is NOT what I wanted to have here. We need to parse WSDL instead of these
 *       strings, originating from PHP. Main reason: missing support for min/maxOccurrence
 *       (which should translate to "required" or less), nillable and more.
 *       There seem to exist well-implemented related libraries
 */
class SoapClientDefinitionParser
{
    /** @var SoapTypeMeta[] */
    protected $types = [];

    /** @var SoapMethodMeta[] */
    protected $methods = [];

    public function __construct(array $soapMethods, array $soapTypes)
    {
        $this->types = $this->parseTypeDefinitions($soapTypes);
        $this->methods = $this->parseMethodDefinitions($soapMethods);
        /*
         eg.:
        $soapMethods = [
            'ProcessOperationResponse ProcessOperation(ProcessOperation $Icinga)'
        ];
        $soapTypes = [
            'struct ProcessOperation {
                     string Action;
                     string Monitor;
                     string ShortDesc;
                     string Problem;
                     string Priority;
                     string Hostname;
                     string Service;
                     string Group;
                }',
            'struct ProcessOperationResponse {
                     string Result;
                }',
        ];
        */
    }

    /**
     * @return string[]
     */
    public function listMethodNames(): array
    {
        return array_keys($this->methods);
    }

    /**
     * @return string[]
     */
    public function getFlatMethodProperties(string $method): array
    {
        $result = [];
        $primitive = ['string', 'int'];
        foreach ($this->methods[$method]->parameters as $parameter) {
            if (in_array($parameter->type, $primitive)) {
                $result[$parameter->name] = $parameter->type;
            } else {
                $type = $this->requireType($parameter->type);
                foreach ($type->parameters as $subType) {
                    $result[$parameter->name . '.' . $subType->name] = $subType->type;
                }
            }
        }

        return $result;
    }

    public static function discover(SoapClient $client): SoapClientDefinitionParser
    {
        return new SoapClientDefinitionParser(
            $client->__getFunctions(),
            $client->__getTypes()
        );
    }

    protected function requireType($name): SoapTypeMeta
    {
        if (isset($this->types[$name])) {
            return $this->types[$name];
        }

        throw new \RuntimeException('No sucht type: ' . $name);
    }

    protected function parseMethodDefinitions(array $soapMethods): array
    {
        $result = [];
        foreach ($soapMethods as $method) {
            $parsed = $this->parseMethod($method);
            $result[$parsed->name] = $parsed;
        }

        return $result;
    }

    protected function parseTypeDefinitions(array $definitions): array
    {
        $result = [];
        foreach ($definitions as $definition) {
            list($typeName, $subTypes) = $this->parseTypeDefinition($definition);
            $result[$typeName] = new SoapTypeMeta($typeName, $subTypes);
        }

        return $result;
    }

    protected function parseTypeDefinition(string $definition): array
    {
        if (! preg_match('/^struct\s+([a-z_][a-z_0-9]*)\s+\{([^}]*)}$/ui', trim($definition), $match)) {
            throw new \RuntimeException('Unsupported type definition: ' . $definition);
        }

        return [$match[1], $this->parseParameters($match[2], ';')];
    }

    protected function parseParameters(string $string, string $separator): array
    {
        $result = [];
        $singleParameterStrings = preg_split(
            '/\s*' . preg_quote($separator, '/') . '\s*/',
            trim($string),
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        foreach ($singleParameterStrings as $parameterString) {
            if (preg_match('/^([a-z_][a-z_0-9]*)\s+\$?([a-z_][a-z_0-9]*)$/ui', trim($parameterString), $match)) {
                $result[] = new SoapParamMeta($match[2], $match[1]);
            } else {
                throw new \RuntimeException('Unable to parse SOAP parameter string: ' . $parameterString);
            }
        }

        return $result;
    }

    protected function parseMethod(string $string): SoapMethodMeta
    {
        $pattern = '/^([a-z_][a-z_0-9]*)\s([a-z_][a-z0-9]*)\((.+?)\)$/ui';
        if (! preg_match($pattern, $string, $match)) {
            return throw new \RuntimeException('Unable to parse SOAP method string: ' . $string);
        }

        return new SoapMethodMeta(
            $match[2],
            $this->parseParameters($match[3], ','),
            $match[1]
        );
    }
}
