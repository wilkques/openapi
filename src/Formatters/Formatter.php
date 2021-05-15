<?php

namespace Wilkques\OpenAPI\Formatters;

abstract class Formatter
{
    /** @var array */
    protected $docs;

    public function __construct(array $docs = null)
    {
        $this->setDocs($docs);
    }

    /**
     * @param string $docs
     * 
     * @return static
     */
    public function setDocs(?array $docs)
    {
        $this->docs = $docs;

        return $this;
    }

    /**
     * @return static
     */
    public function getDocs()
    {
        return $this->docs;
    }

    /**
     * @return string|false
     */
    abstract public function format();
}
