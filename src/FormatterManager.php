<?php

namespace Wilkques\OpenAPI;

class FormatterManager
{
    /** @var GeneratorOpenAPIDoc */
    private $generatorOpenAPIDoc;
    /** @var string */
    private $format = "json";
    /** @var Formatters\JsonFormatter|Formatters\YamlFormatter */
    private $formatter;
    /** @var array */
    private $setMethods = [
        'generator'
    ];

    /**
     * @param GeneratorSwaggerDoc $generatorSwaggerDoc
     * 
     * @return static
     */
    public function setGeneratorSwaggerDoc(GeneratorOpenAPIDoc $generatorSwaggerDoc)
    {
        $this->generatorSwaggerDoc = $generatorSwaggerDoc;

        return $this;
    }

    /**
     * @return GeneratorSwaggerDoc
     */
    public function getGeneratorSwaggerDoc()
    {
        return $this->generatorSwaggerDoc;
    }

    /**
     * @param string $format
     * 
     * @return static
     */
    public function setFormat(string $format = "json")
    {
        $this->format = strtolower($format);

        return $this;
    }

    /**
     * @return string
     */
    public function getFormat()
    {
        return $this->format;
    }

    /**
     * @throws LaravelSwaggerException
     * 
     * @return static
     */
    public function setFormatter()
    {
        switch ($this->getFormat()) {
            case "json":
                $this->formatter = new Formatters\JsonFormatter($this->getGenerate());
                break;
            case "yaml":
                $this->formatter = new Formatters\YamlFormatter($this->getGenerate());
                break;
            default:
                throw new LaravelSwaggerException('Invalid format passed');
                break;
        }

        return $this;
    }

    /**
     * @return Formatters\JsonFormatter|Formatters\YamlFormatter
     */
    public function getFormatter()
    {
        return $this->formatter;
    }

    /**
     * @return string|false
     */
    public function output()
    {
        return $this->setFormatter()->getFormatter()->format();
    }

    public function __call($method, $arguments)
    {
        in_array($method, $this->setMethods) && $method = sprintf("set%s", ucfirst(trim($method)));

        return $this->getGeneratorSwaggerDoc()->setFormatterManager($this)->$method(...$arguments);
    }
}
