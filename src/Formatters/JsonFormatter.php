<?php

namespace Wilkques\OpenAPI\Formatters;

use Wilkques\OpenAPI\OpenAPIException;

class JsonFormatter extends Formatter
{
    /**
     * @return string|false
     */
    public function format()
    {
        if (!extension_loaded('json')) {
            throw new OpenAPIException('JSON extension must be loaded to use the json output format');
        }

        return json_encode($this->getDocs(), JSON_PRETTY_PRINT);
    }
}
