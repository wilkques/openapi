<?php

namespace Wilkques\OpenAPI\Parameters;

interface ParameterGenerator
{
    public function getParameters();

    public function getParamLocation();
}
