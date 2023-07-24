<?php

namespace Wilkques\OpenAPI\DataObjects;

class Middleware
{
    /** @var string */
    private $name;

    /** @var array */
    private $parameters;

    public function __construct(string $middleware)
    {
        $tokens = explode(':', $middleware, 2);

        $this->name = $tokens[0];

        $this->parameters = isset($tokens[1]) ? explode(',', $tokens[1]) : [];
    }

    /**
     * @return string
     */
    public function name()
    {
        return $this->name;
    }

    /**
     * @return array
     */
    public function parameters()
    {
        return $this->parameters;
    }
}
