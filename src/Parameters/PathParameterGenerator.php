<?php

namespace Wilkques\OpenAPI\Parameters;

use Illuminate\Support\Str;
use Wilkques\OpenAPI\Parameters\Contracts\ParameterGenerator;

class PathParameterGenerator implements ParameterGenerator
{
    /** @var string */
    protected $uri;

    /** @var array */
    protected $pathsDoc;

    public function __construct(string $uri, array $pathsDoc = [])
    {
        $this->setUri($uri)->setPathsDoc($pathsDoc);
    }

    /**
     * @param string $uri
     * 
     * @return static
     */
    public function setUri(string $uri)
    {
        $this->uri = $uri;

        return $this;
    }

    /**
     * @return string
     */
    public function getUri()
    {
        return $this->uri;
    }

    /**
     * @param array $pathsDoc
     * 
     * @return static
     */
    public function setPathsDoc(array $pathsDoc)
    {
        $this->pathsDoc = $pathsDoc;

        return $this;
    }

    /**
     * @return array
     */
    public function getPathsDoc()
    {
        return $this->pathsDoc;
    }

    /**
     * @return array
     */
    public function getParameters()
    {
        $parameters = [];
        $pathVariables = $this->getAllVariablesFromUri();

        foreach ($pathVariables as $variable) {
            $name = strip_optional_char($variable);

            $parameter = [
                'in' => $this->getParamLocation(),
                'name' => $name,
                'required' => $this->isPathVariableRequired($variable),
                'description' => '',
                'schema' => [
                    'type' => 'string', //best guess for a variable in the path
                ]
            ];

            if (array_key_exists($name, $this->pathsDoc)) {
                $parameter = array_merge($parameter, $this->getPathsDoc()[$name]);
            }

            $parameters[] = $parameter;
        }

        return empty($parameters) ? $parameters : compact('parameters');
    }

    /**
     * @return array
     */
    private function getAllVariablesFromUri()
    {
        preg_match_all('/{(\w+\??)}/', $this->getUri(), $pathVariables);

        return $pathVariables[1];
    }

    /**
     * @return string
     */
    public function getParamLocation()
    {
        return 'path';
    }

    /**
     * @return bool
     */
    private function isPathVariableRequired($pathVariable)
    {
        return !Str::contains($pathVariable, '?');
    }
}
