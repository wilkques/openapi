<?php

namespace Wilkques\OpenAPI\Parameters;

use Illuminate\Support\Str;

class PathParameterGenerator implements ParameterGenerator
{
    protected $uri;

    public function __construct($uri)
    {
        $this->uri = $uri;
    }

    public function getParameters()
    {
        $parameters = [];
        $pathVariables = $this->getAllVariablesFromUri();

        foreach ($pathVariables as $variable) {
            $parameters[] = [
                'in' => $this->getParamLocation(),
                'name' => strip_optional_char($variable),
                'type' => 'string', //best guess for a variable in the path
                'required' => $this->isPathVariableRequired($variable),
                'description' => '',
            ];
        }

        return empty($parameters) ? $parameters : compact('parameters');
    }

    private function getAllVariablesFromUri()
    {
        preg_match_all('/{(\w+\??)}/', $this->uri, $pathVariables);

        return $pathVariables[1];
    }

    public function getParamLocation()
    {
        return 'path';
    }

    private function isPathVariableRequired($pathVariable)
    {
        return !Str::contains($pathVariable, '?');
    }
}
