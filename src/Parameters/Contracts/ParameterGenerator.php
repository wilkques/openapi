<?php

namespace Wilkques\OpenAPI\Parameters\Contracts;

interface ParameterGenerator
{
    /**
     * @return array
     */
    public function getParameters();

    /**
     * @return string
     */
    public function getParamLocation();
}
