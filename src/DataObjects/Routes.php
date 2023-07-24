<?php

namespace Wilkques\OpenAPI\DataObjects;

use Illuminate\Config\Repository as Config;
use Illuminate\Routing\Router;
use Wilkques\OpenAPI\Helpers\Collection;
use Wilkques\OpenAPI\PhpDocBlock;

class Routes extends DataObjects
{
    /** @var array */
    protected $excludeRoute = [];

    /** @var array */
    protected $filterRoute = [];

    /** @var Router */
    protected $router;

    /**
     * @param PhpDocBlock $docParser
     * @param Collection $collection
     */
    public function __construct(PhpDocBlock $docParser, Config $config, Router $router, Collection $collection)
    {
        parent::__construct($docParser, $config);

        $this->setBindings($collection);

        $this->router = $router;
    }

    /**
     * @param string|null $filterRoute
     * 
     * @return static
     */
    public function setFilterRoute(string $filterRoute = null)
    {
        preg_match_all('/[^,]+/i', $filterRoute, $matches);

        $this->filterRoute = array_shift($matches);

        return $this;
    }

    /**
     * @return array|null
     */
    public function getFilterRoute()
    {
        return $this->filterRoute;
    }

    /**
     * @param string|null $excludeRoute
     * 
     * @return static
     */
    public function setExcludeRoute(string $excludeRoute = null)
    {
        preg_match_all('/[^,]+/i', $excludeRoute, $matches);

        $this->excludeRoute = array_shift($matches);

        return $this;
    }

    /**
     * @return array|null
     */
    public function getExcludeRoute()
    {
        return $this->excludeRoute;
    }

    /**
     * @return static
     */
    public function handle()
    {
        $this->collection($this->router->getRoutes())->each(function ($route) {
            $this->route($route);

            if (!$route = $this->getRoute()) {
                return;
            }

            $this->collection($route->methods())->each(function ($httpMethod) {
                $this->methods($httpMethod);
            });
        });

        return $this;
    }

    /**
     * Check route
     * 
     * @param \Illuminate\Routing\Route $route
     * 
     * @return static
     */
    protected function route(\Illuminate\Routing\Route $route)
    {
        $route = new Route($route, $this->docParser, $this->config, $this->router->getMiddleware());

        // comment is not deprecated or except route
        if ($route->isExclude()) {
            return;
        }

        // exclude route and filter route
        $route->setExcludeRoute($this->getExcludeRoute())->setFilterRoute($this->getFilterRoute());

        // if route is filter or except
        if ($route->isRouteFilterOrExcept()) {
            return;
        }

        return $this->setRoute($route);
    }

    /**
     * Check Controller method
     * 
     * @param string $httpMethod HTTP METHOD GET POST PUT DELETE OPTIONS...etc
     */
    protected function methods($httpMethod)
    {
        if (in_array($httpMethod, $this->getConfig('ignoredMethods'))) {
            return;
        }

        [$abstract, $method] = $this->getActionClassAndMethods();

        $reflectionClassOrFunction = $this->getRoute()->getReflection();

        $reflectionMethod = null;

        if (
            $reflectionClassOrFunction instanceof \ReflectionClass &&
            !$reflectionMethod = $this->reflectionMethod($reflectionClassOrFunction, $method)
        ) {
            return;
        }

        // controller method
        $methodOrClosure = new MethodOrClosure(
            $httpMethod,
            $this->docParser,
            $this->config,
            $this->getRoute(),
            $reflectionMethod ?: $reflectionClassOrFunction,
            // method request
            new FormRequest(
                $this->docParser,
                $this->config,
                $this->getRoute()
            )
        );

        // cmethod comment is not deprecated or except route
        if ($methodOrClosure->isExclude()) {
            return;
        }

        // controller has server tags
        if ($servers = $this->getRoute()->servers()) {
            $this->buildBindings($this->collection(['servers' => $servers]), $abstract);
        }

        // if abstruct is Closure
        if (!$method) {
            $this->buildBindings($this->collection([$methodOrClosure->handle()->items()]));

            return;
        }

        $this->buildBindings($this->collection([$method => $methodOrClosure->handle()->items()]), $abstract);
    }
}
