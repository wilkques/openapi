<?php

namespace Wilkques\OpenAPI\Parameters\Contracts;

interface ParameterGenerator
{
    public function getParameters();

    public function getParamLocation();
}
