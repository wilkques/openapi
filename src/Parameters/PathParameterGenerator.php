<?php

namespace Wilkques\OpenAPI\Parameters;

use Wilkques\OpenAPI\Helpers\Collection;
use Wilkques\OpenAPI\Parameters\Contracts\ParameterGenerator;

class PathParameterGenerator implements ParameterGenerator
{
    /** @var string */
    protected $uri;

    /** @var Collection|null */
    protected $pathsDoc;

    public function __construct(string $uri, Collection $pathsDoc = null)
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
     * @param Collection|null $pathsDoc
     * 
     * @return static
     */
    public function setPathsDoc(Collection $pathsDoc = null)
    {
        $this->pathsDoc = $pathsDoc;

        return $this;
    }

    /**
     * @return Collection|null
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

            if ($this->getPathsDoc() && $this->getPathsDoc()->has($name)) {
                $parameter = array_replace_recursive($parameter, $this->getPathsDoc()->get($name)->toArray());
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
        return !str_contains($pathVariable, '?');
    }
}
