<?php

namespace Wilkques\OpenAPI\Formatters;

use Wilkques\OpenAPI\OpenAPIException;

class YamlFormatter extends Formatter
{
    /**
     * @return string
     */
    public function format()
    {
        if (!extension_loaded('yaml')) {
            throw new OpenAPIException('YAML extension must be loaded to use the yaml output format');
        }

        return yaml_emit($this->getDocs());
    }
}
