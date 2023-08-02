<?php

namespace Wilkques\OpenAPI;

use Illuminate\Support\Str;

/**
 * @method static format(string $format)
 * @method static generator(Generator $generator)
 * @method string output()
 * @method static outputDoc(string $file)
 */
class GeneratorOpenAPIDoc
{
    /** @var Generator */
    private $generator;

    /** @var FormatterManager */
    private $formatterManager;

    /** @var array */
    private $setMethods = [
        'format'
    ];

    /**
     * @param Generator|null $generator
     */
    public function __construct($generator = null)
    {
        $this->setGenerator($generator);
    }

    /**
     * @param string $dir
     * 
     * @return static
     */
    public function mkdir(string $dir)
    {
        if (!is_dir($dir)) {
            if (false === @mkdir($dir, 0775, true)) {
                throw new \RuntimeException(sprintf('Unable to create the %s directory', $dir));
            }
        }

        return $this;
    }

    /**
     * @param string $filePath
     * 
     * @return static
     */
    public function filePutCentent(string $filePath)
    {
        file_put_contents($filePath, $this->output());

        return $this;
    }

    /**
     * @param string $file
     * 
     * @return static
     */
    public function outputDoc(string $filePath)
    {
        [
            'dirname'   => $dir,
            'extension' => $extension,
        ] = pathinfo($filePath);

        $this->setFormat($extension);

        return $this->mkdir($dir)->filePutCentent($filePath);
    }

    /**
     * @param Generator|null $generator
     * 
     * @return static
     */
    public function setGenerator($generator)
    {
        $this->generator = $generator;

        return $this;
    }

    /**
     * @return Generator
     */
    public function getGenerator()
    {
        return $this->generator;
    }

    /**
     * @param FormatterManager $formatterManager
     * 
     * @return static
     */
    public function setFormatterManager(FormatterManager $formatterManager)
    {
        $this->formatterManager = $formatterManager;

        return $this;
    }

    /**
     * @return FormatterManager
     */
    public function getFormatterManager()
    {
        return $this->formatterManager;
    }

    /**
     * @return FormatterManager
     */
    public function newFormatterManager()
    {
        return new FormatterManager;
    }

    /**
     * @param string $method
     * @param array $arguments
     * 
     * @return mixed|static
     */
    public function __call(string $method, array $arguments)
    {
        in_array($method, $this->setMethods) && $method = sprintf("set%s", ucfirst(trim($method)));

        $instance = $this->getFormatterManager() ?: $this->newFormatterManager();

        return $instance->setGeneratorSwaggerDoc($this)->$method(...$arguments);
    }

    /**
     * @param string $method
     * @param array $arguments
     * 
     * @return static
     */
    public static function __callStatic(string $method, array $arguments)
    {
        return (new static)->$method(...$arguments);
    }
}
