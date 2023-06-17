<?php

namespace Wilkques\OpenAPI\Formatters;

class JsonFormatter extends Formatter
{
    /**
     * @return string|false
     */
    public function format()
    {
        if (!extension_loaded('json')) {
            throw new \Wilkques\OpenAPI\Exceptions\OpenAPIException('JSON extension must be loaded to use the json output format');
        }

        return json_encode($this->getDocs(), JSON_PRETTY_PRINT);
    }
}
