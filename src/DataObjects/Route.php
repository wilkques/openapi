<?php

namespace Wilkques\OpenAPI\DataObjects;

use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Arr;
use Illuminate\Config\Repository as Config;
use Illuminate\Support\Str;
use Wilkques\OpenAPI\PhpDocBlock;

class Route extends DataObjects
{
    /** @var array */
    protected $middleware;

    /** @var array */
    protected $laravelMiddlewares;

    /** @var array */
    protected $security;

    /** @var array */
    protected $excludeRoute = [];

    /** @var array */
    protected $filterRoute = [];

    /** @var \ReflectionClass|\ReflectionFunction */
    protected $reflectionClassOrFunction;

    /**
     * @param LaravelRoute $route
     * @param PhpDocBlock $docParser
     * @param Config $config
     * @param array $middlewares
     */
    public function __construct(LaravelRoute $route, PhpDocBlock $docParser, Config $config, array $middlewares)
    {
        parent::__construct($docParser, $config);

        $this->laravelMiddlewares = $middlewares;

        $this->setRoute($route)->boot();
    }

    /**
     * @return static
     */
    protected function boot()
    {
        $this->middleware = $this->formatMiddleware();

        [$abstract] = $this->getActionClassAndMethods();

        if ($abstract === 'Closure') {
            $abstract = $this->action('uses');
        }

        $this->reflectionClassOrFunction = $this->reflectionAbstract($abstract);

        $this->whenParseDocBlock(fn () => $this->setDocComment($this->reflectionClassOrFunction), false);

        $this->addActionScopes();

        return $this;
    }

    /**
     * @return \ReflectionClass|\ReflectionFunction
     */
    public function getReflection()
    {
        return $this->reflectionClassOrFunction;
    }

    /**
     * @param array|null $filterRoute
     * 
     * @return static
     */
    public function setFilterRoute(array $filterRoute = null)
    {
        $this->filterRoute = $filterRoute;

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
     * @param array|null $excludeRoute
     * 
     * @return static
     */
    public function setExcludeRoute(array $excludeRoute = null)
    {
        $this->excludeRoute = $excludeRoute;

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
     * @return string
     */
    public function originalUri()
    {
        $uri = $this->getRoute()->uri();

        if (!Str::startsWith($uri, '/')) {
            $uri = '/' . $uri;
        }

        return $uri;
    }

    /**
     * @return string
     */
    public function uri()
    {
        return strip_optional_char($this->originalUri());
    }

    /**
     * @return array
     */
    public function middleware()
    {
        return $this->middleware;
    }

    /**
     * @return array
     */
    public function methods()
    {
        return array_map('strtolower', $this->getRoute()->methods());
    }

    /**
     * @param array $security
     * 
     * @return static
     */
    public function setSecurity($security)
    {
        $this->security = $security;

        return $this;
    }

    /**
     * @return array
     */
    public function getSecurity()
    {
        return $this->security;
    }

    /**
     * @return array
     */
    protected function formatMiddleware()
    {
        $middleware = $this->action('middleware') ?? [];

        return array_map(function ($middleware) {
            return new Middleware($middleware);
        }, Arr::wrap($middleware));
    }

    /**
     * @return static
     */
    protected function addActionScopes()
    {
        foreach ($this->middleware() as $middleware) {
            if ($this->isPassportScopeMiddleware($middleware)) {
                $this->setSecurity([
                    'oauth2' => $middleware->parameters(),
                ]);
            }
        }

        return $this;
    }

    /**
     * @param \Wilkques\OpenAPI\DataObjects\Middleware  $middleware
     * 
     * @return boolean
     */
    private function isPassportScopeMiddleware(\Wilkques\OpenAPI\DataObjects\Middleware $middleware)
    {
        $resolver = $this->getMiddlewareResolver($middleware->name());

        return $resolver === \Laravel\Passport\Http\Middleware\CheckScopes::class ||
            $resolver === \Laravel\Passport\Http\Middleware\CheckForAnyScope::class;
    }

    /**
     * @param string $middleware
     * 
     * @return mixed|null
     */
    private function getMiddlewareResolver(string $middleware)
    {
        return $this->laravelMiddlewares[$middleware] ?? null;
    }

    /**
     * @return array
     */
    public function servers()
    {
        return $this->whenParseDocBlock(
            fn () => $this->parseDocBlock(
                fn (\phpDocumentor\Reflection\DocBlock $docBlock) => $this->targetDocWithTag(
                    $docBlock,
                    'Servers',
                    fn ($docsBody) => $this->collection($docsBody)->map(fn ($item) => $this->collection($item))
                )
            )
        );
    }

    /**
     * @return boolean
     */
    public function isRouteFilterOrExcept()
    {
        return $this->onlyNamespace() ||
            $this->routeExcept() ||
            $this->getFilterRoute() && $this->isFilteredRoute() ||
            $this->getExcludeRoute() && $this->isExcludeRoute();
    }

    /**
     * @return boolean
     */
    private function onlyNamespace()
    {
        return !empty($namespace = $this->getConfig('only.namespace')) &&
            !Str::containsAll(
                Str::start($this->getActionNamespace(), "\\"),
                array_map(
                    fn ($item) => Str::start($item, '\\'),
                    $namespace
                )
            );
    }

    /**
     * @return boolean
     */
    private function routeExcept()
    {
        return !empty($this->getConfig('exclude.routes')) &&
            $this->excludeRouteUri($this->uri()) ||
            $this->excludeRouteName($this->getAs());
    }

    /**
     * @param string $uri
     * 
     * @return boolean
     */
    private function excludeRouteUri(string $uri)
    {
        return in_array(
            Str::start($uri, "/"),
            array_map(
                fn ($item) => Str::start($item, '/'),
                $this->getConfig('exclude.routes.uri')
            )
        );
    }

    /**
     * @param string|null $name
     * 
     * @return boolean
     */
    private function excludeRouteName(string $name = null)
    {
        return is_null($name) ? false : in_array($name, $this->getConfig("exclude.routes.name", []));
    }

    /**
     * @return boolean
     */
    private function isFilteredRoute()
    {
        foreach ($this->getFilterRoute() as $routeFilter) {
            return $this->pregRoute($routeFilter);
        }
    }

    /**
     * @return boolean
     */
    private function isExcludeRoute()
    {
        foreach ($this->getExcludeRoute() as $routeExcept) {
            return $this->pregRoute($routeExcept);
        }
    }

    private function pregRoute($route)
    {
        return !preg_match('/^' . preg_quote($route, '/') . '/', $this->uri());
    }
}
