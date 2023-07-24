<?php

namespace Wilkques\OpenAPI\DataObjects;

use Illuminate\Config\Repository as Config;
use ReflectionClass;
use Wilkques\OpenAPI\PhpDocBlock;

class FormRequest extends DataObjects
{
    /** @var ReflectionClass */
    protected $reflectionClass;

    /**
     * @param PhpDocBlock $docParser
     * @param Config $config
     * @param Route $route
     * @param ReflectionClass $reflectionClass
     */
    public function __construct(
        PhpDocBlock $docParser,
        Config $config,
        Route $route,
        ReflectionClass $reflectionClass = null
    ) {
        parent::__construct($docParser, $config);

        if ($reflectionClass) {
            $this->setReflectionClass($reflectionClass)
                ->whenParseDocBlock(fn () => $this->setDocComment($reflectionClass->getMethod('rules')), false);
        }

        $this->setRoute($route)->setBindings($this->collection());
    }

    /**
     * @param ReflectionClass $reflectionClass
     * 
     * @return static
     */
    public function setReflectionClass(ReflectionClass $reflectionClass)
    {
        $this->reflectionClass = $reflectionClass;

        return $this;
    }

    /**
     * @return ReflectionClass
     */
    public function getReflectionClass()
    {
        return $this->reflectionClass;
    }

    /**
     * @return static
     */
    public function handle()
    {
        if (!$this->getDocComment()) {
            $this->whenParseDocBlock(fn () => $this->setDocComment($this->getReflectionClass()->getMethod('rules')), false);
        }

        return $this->buildBindingWithParseDoc(fn () => $this->fields(), 'fields')
            ->buildBindings(fn () => $this->formRequest()->rules() ?: [], 'rules');
    }

    /**
     * @return array
     */
    protected function fields()
    {
        return $this->parseDocBlock(
            fn (\phpDocumentor\Reflection\DocBlock $docBlock) => $this->targetDocWithTag($docBlock, 'Fields')
        );
    }

    /**
     * @return mixed|\Illuminate\Foundation\Http\FormRequest
     */
    protected function formRequest()
    {
        return $this->getReflectionClass()->newInstance();
    }
}
