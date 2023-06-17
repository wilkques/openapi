<?php

namespace Wilkques\OpenAPI\Formatters;

class YamlFormatter extends Formatter
{
    /**
     * @return string
     */
    public function format()
    {
        if (!extension_loaded('yaml')) {
            throw new \Wilkques\OpenAPI\Exceptions\OpenAPIException('YAML extension must be loaded to use the yaml output format');
        }

        return \yaml_emit($this->getDocs());
    }
}
