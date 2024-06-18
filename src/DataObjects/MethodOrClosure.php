<?php

namespace Wilkques\OpenAPI\DataObjects;

use Illuminate\Config\Repository as Config;
use phpDocumentor\Reflection\DocBlock;
use ReflectionMethod;
use Wilkques\OpenAPI\DataObjects\FormRequest;
use Wilkques\OpenAPI\PhpDocBlock;

class MethodOrClosure extends DataObjects
{
    /** @var ReflectionMethod|ReflectionFunction */
    protected $reflectionMethodOrFunction;

    /** @var array */
    protected $rules;

    /** @var string */
    protected $httpMethod;

    /** @var DocBlock */
    protected $docBlock;

    /** @var FormRequest */
    protected $formRequest;

    /**
     * @param string $httpMethod
     * @param PhpDocBlock $docParser
     * @param Config $config
     * @param Route $route
     * @param ReflectionMethod|ReflectionFunction $reflectionMethodOrFunction
     * @param FormRequest $formRequest
     */
    public function __construct(
        string $httpMethod,
        PhpDocBlock $docParser,
        Config $config,
        Route $route,
        $reflectionMethodOrFunction,
        FormRequest $formRequest
    ) {
        parent::__construct($docParser, $config);

        $this->reflectionMethodOrFunction = $reflectionMethodOrFunction;

        $this->formRequest = $formRequest;

        $this->setDocComment($reflectionMethodOrFunction);

        $this->setRoute($route)
            ->setHttpMethod($httpMethod)
            ->setDocBlock()
            ->setBindings(
                $this->collection(
                    [
                        'method'    => $this->getHttpMethod(),
                        'uri'       => $this->getRoute()->uri(),
                        'originUri' => $this->getRoute()->originalUri(),
                    ]
                )
            );
    }

    /**
     * @param string $httpMethod
     * 
     * @return static
     */
    public function setHttpMethod(string $httpMethod)
    {
        $this->httpMethod = $httpMethod;

        return $this;
    }

    /**
     * @return string
     */
    public function getHttpMethod()
    {
        return $this->httpMethod;
    }

    /**
     * @return static
     */
    public function setDocBlock()
    {
        $this->docBlock = $this->parseDocBlock();

        return $this;
    }

    /**
     * @return DocBlock
     */
    public function getDocBlock()
    {
        return $this->docBlock;
    }

    public function handle()
    {
        // building security schema
        return $this->buildBindings($this->getRoute()->getSecurity(), 'security')
            // building tag deprecated
            ->buildBindingWithParseDoc(fn () => $this->deprecated(), 'deprecated', false)
            // building tag summary
            ->buildBindingWithParseDoc(fn () => $this->summary(), 'summary')
            // building tag description
            ->buildBindingWithParseDoc(fn () => $this->description(), 'description')
            // building url path
            ->buildBindingWithParseDoc(fn () => $this->path(), 'path')
            // building request
            ->buildBindingWithParseDoc(fn () => $this->request(), 'request')
            // building validation request or url
            ->buildBindingWithParseDoc(fn () => $this->rules(), 'rules')
            // building response
            ->buildBindingWithParseDoc(fn () => $this->response(), 'response');
    }

    /**
     * @return bool
     */
    protected function deprecated()
    {
        return $this->targetDocWithTag(
            $this->getDocBlock(),
            'deprecated',
            fn ($docsBody, DocBlock $docBlock) => $docBlock->hasTag('deprecated')
        );
    }

    /**
     * building url path
     * 
     * @return array
     */
    protected function path()
    {
        return $this->targetDocWithTag($this->getDocBlock(), 'Path');
    }

    /**
     * building request
     * 
     * @return array
     */
    protected function request()
    {
        return $this->targetDocWithTag($this->getDocBlock(), 'Request');
    }

    /**
     * building validation request or url
     * 
     * @return Collection|[]
     */
    protected function rules()
    {
        if (!$methodOrClosure = $this->reflectionMethodOrFunction) {
            return [];
        }

        $parameters = $methodOrClosure->getParameters();

        foreach ($parameters as $parameter) {
            // fix issues https://github.com/mtrajano/laravel-swagger/issues/60
            $reflectionAbstract = $parameter->getType() && !$parameter->getType()->isBuiltin()
                ? $this->reflectionAbstract($parameter->getType()->getName())
                : null;

            // fix https://github.com/mtrajano/laravel-swagger/issues/60 bug
            if ($reflectionAbstract && $reflectionAbstract->isSubclassOf(\Illuminate\Foundation\Http\FormRequest::class)) {
                return $this->formRequest->setReflectionClass($reflectionAbstract)->handle()->items();
            }
        }

        return [];
    }

    /**
     * building response
     * 
     * @return array
     */
    protected function response()
    {
        return $this->targetDocWithTag($this->getDocBlock(), 'Response');
    }
}
