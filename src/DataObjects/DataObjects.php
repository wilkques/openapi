<?php

namespace Wilkques\OpenAPI\DataObjects;

use Illuminate\Config\Repository as Config;
use Illuminate\Support\Str;
use phpDocumentor\Reflection\DocBlock;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use Wilkques\OpenAPI\Helpers\Collection;
use Wilkques\OpenAPI\PhpDocBlock;
use Wilkques\OpenAPI\Traits\CollectionTrait;

abstract class DataObjects
{
    use CollectionTrait;

    /** @var Collection */
    protected $bindings = [];

    /** @var PhpDocBlock */
    protected $docParser;

    /** @var mixed|\Illuminate\Config\Repository */
    protected $config;

    /** @var Route|\Illuminate\Routing\Route */
    protected $route;

    /** @var string */
    protected $configPrefix = 'openapi';

    /**
     * doc comment
     * 
     * @var string
     */
    protected $docComment;

    /**
     * @param PhpDocBlock $docParser
     * @param Config $config
     */
    public function __construct(PhpDocBlock $docParser, Config $config)
    {
        $this->config = $config;

        $this->docParser = $docParser;
    }

    /**
     * @param Route|\Illuminate\Routing\Route $route
     * 
     * @return static
     */
    public function setRoute($route)
    {
        $this->route = $route;

        return $this;
    }

    /**
     * @return Route|\Illuminate\Routing\Route
     */
    public function getRoute()
    {
        return $this->route;
    }

    /**
     * @return string
     */
    public function getAs()
    {
        return $this->action('as');
    }

    /**
     * @return string
     */
    public function getActionNamespace()
    {
        return $this->action('namespace');
    }

    /**
     * @param string|null $key
     * 
     * @return mixed
     */
    public function action(string $key = null)
    {
        return $this->getRoute()->getAction($key);
    }

    /**
     * @return string
     */
    public function getActionName(): string
    {
        return $this->getRoute()->getActionName();
    }

    /**
     * @param bool|true $default
     * 
     * @return bool|true
     */
    protected function isParseDocBlock($default = true)
    {
        return $this->getConfig('parseDocBlock', $default);
    }

    /**
     * @return bool|true
     */
    protected function isNotParseDocBlock()
    {
        return !$this->isParseDocBlock(false);
    }

    /**
     * @return array
     */
    protected function getActionClassAndMethods()
    {
        return Str::parseCallback($this->getActionName());
    }

    /**
     * @param string|callable|\Closure $abstract
     * 
     * @return ReflectionClass|null
     */
    protected function reflectionAbstract($abstract)
    {
        if (!$abstract) {
            return null;
        }

        if (is_callable($abstract)) {
            return new ReflectionFunction($abstract);
        }

        return new ReflectionClass($abstract);
    }

    /**
     * @param ReflectionClass $reflectionClass
     * 
     * @return ReflectionMethod
     */
    protected function reflectionMethod($reflectionClass, $method): ?ReflectionMethod
    {
        $abstract = $reflectionClass->getName();

        if (!$abstract || !$method || ($abstract && !method_exists($abstract, $method))) {
            return null;
        }

        return $reflectionClass->getMethod($method);
    }

    /**
     * @param ReflectionClass|ReflectionMethod $reflection
     * 
     * @return static
     */
    protected function setDocComment($reflection)
    {
        $this->docComment = $reflection ? ($reflection->getDocComment() ?: '') : '';

        return $this;
    }

    /**
     * @return string|false
     */
    protected function getDocComment()
    {
        return $this->docComment;
    }

    /**
     * @param \Closure $callback
     * 
     * @return mixed|null|\phpDocumentor\Reflection\DocBlock
     */
    protected function parseDocBlock(\Closure $callback = null, $comment = null)
    {
        $comment = $comment ?: $this->getDocComment();

        if (!$callback) {
            return $this->docParser->parseDocBlock($comment);
        }

        return $this->docParser->parseDocBlock($comment, fn (DocBlock $docBlock) => $callback($docBlock));
    }

    /**
     * @param DocBlock|null $docBlock
     * @param string $tagName
     * @param \Closure|null $callback
     * @param array|[] $target
     * 
     * @return array|mixed|[]
     */
    protected function targetDocWithTag($docBlock, string $tagName, \Closure $callback = null, array $target = [])
    {
        if (!$docBlock || !$docBlock->hasTag($tagName)) {
            return $target;
        }

        $targetDoc = $this->docParser->getDocsTagsByName($docBlock, $tagName);

        if (!empty($targetDoc)) {
            $docsBody = $this->docParser->docsBody(array_shift($targetDoc));

            $target = $callback ? $callback($docsBody, $docBlock, $targetDoc) : $docsBody;
        }

        return $target;
    }

    /**
     * @return string
     */
    protected function summary()
    {
        return $this->whenParseDocBlock(
            fn () => $this->parseDocBlock(
                fn (DocBlock $docBlock) => $docBlock->getSummary()
            )
        );
    }

    /**
     * @return string
     */
    protected function description()
    {
        return $this->whenParseDocBlock(
            fn () => $this->parseDocBlock(
                fn (DocBlock $docBlock) => (string) $docBlock->getDescription()
            )
        );
    }

    /**
     * check config.openapi.parseDocBlock. if true run callback
     * 
     * @param \Closure $callback
     * @param mixed $default
     * 
     * @return mixed|[]
     */
    protected function whenParseDocBlock(\Closure $callback, $default = [])
    {
        if ($this->isNotParseDocBlock()) {
            return $default;
        }

        return $callback();
    }

    /**
     * @param string $tagName
     * 
     * @return bool
     */
    protected function hasTag(string $tagName)
    {
        return $this->parseDocBlock(fn (DocBlock $docBlock) => $docBlock->hasTag($tagName));
    }

    /**
     * @return bool
     */
    public function isExclude()
    {
        return $this->hasTag('exclude');
    }

    /**
     * @param string|null $key
     * @param mixed $default
     * 
     * @return mixed
     */
    public function getConfig($key = null, $default = null)
    {
        return $this->config->get("{$this->configPrefix}.{$key}", $default);
    }

    /**
     * @param \Wilkques\OpenAPI\Collection $bindings
     * 
     * @return static
     */
    public function setBindings($bindings)
    {
        $this->bindings = $bindings;

        return $this;
    }

    /**
     * @return \Wilkques\OpenAPI\Collection
     */
    public function getBindings()
    {
        return $this->bindings;
    }

    /**
     * @return Collection
     */
    public function items()
    {
        return $this->getBindings();
    }

    /**
     * @param string|null $key
     * @param mixed|null $key
     * 
     * @return mixed
     */
    public function item($key = null, $default = null)
    {
        return $this->items()->get($key, $default);
    }

    /**
     * @param callback|\Closure|mixed $values
     * @param string|null $key
     * 
     * @return static
     */
    protected function buildBindings($values, $key = null)
    {
        if (is_callable($values)) {
            $values = $values();
        }

        return $this->setBindings(
            $this->getBindings()->when(
                $values,
                fn (Collection $collection, $target) => $this->bindingsHandle($collection, $target, $key)
            )
        );
    }

    /**
     * @param Collection $collection
     * @param mixed $target
     * @param string $key
     * 
     * @return Collection
     */
    protected function bindingsHandle(Collection $collection, $target, $key)
    {
        if (!$key) {
            return $collection->push($target);
        }

        if (!(is_array($target) or $target instanceof Collection)) {
            return $collection->put($key, $target);
        }

        $children = $this->collection();

        foreach ($target as $index => $value) {
            if (is_array($value) and !($value instanceof Collection)) {
                $value = $this->collection($value);
            }

            $children->put($index, $value);
        }

        if ($collection->has($key)) {
            $children = $collection->get($key)->mergeRecursiveDistinct($children);
        }

        return $collection->put($key, $children);
    }

    /**
     * @param callback|\Closure $callback
     * @param string $tagName
     * @param mixed|[] $default
     * 
     * @return static
     */
    protected function buildBindingWithParseDoc($callback, $tagName, $default = [])
    {
        return $this->buildBindings(
            $this->whenParseDocBlock($callback, $default),
            $tagName
        );
    }
}
