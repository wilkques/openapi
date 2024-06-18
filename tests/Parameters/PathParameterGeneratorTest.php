<?php

namespace Wilkques\OpenAPI\Tests\Parameters;

use Wilkques\OpenAPI\Parameters\PathParameterGenerator;
use Wilkques\OpenAPI\Tests\TestCase;

class PathParameterGeneratorTest extends TestCase
{
    public function testRequiredParameter()
    {
        ['parameters' => $pathParameters] = $this->getPathParameters('/users/{id}');

        $this->assertSame('path', $pathParameters[0]['in']);
        $this->assertSame('id', $pathParameters[0]['name']);
        $this->assertSame(true, $pathParameters[0]['required']);
    }

    public function testOptionalParameter()
    {
        ['parameters' => $pathParameters] = $this->getPathParameters('/users/{id?}');

        $this->assertSame(false, $pathParameters[0]['required']);
    }

    public function testMultipleParameters()
    {
        ['parameters' => $pathParameters] = $this->getPathParameters('/users/{username}/{id?}');

        $this->assertSame('username', $pathParameters[0]['name']);
        $this->assertSame(true, $pathParameters[0]['required']);

        $this->assertSame('id', $pathParameters[1]['name']);
        $this->assertSame(false, $pathParameters[1]['required']);
    }

    public function testEmptyParameters()
    {
        $pathParameters = $this->getPathParameters('/users');

        $this->assertEmpty($pathParameters);
    }

    private function getPathParameters($uri, $pathsDoc = null)
    {
        return (new PathParameterGenerator($uri, $pathsDoc))->getParameters();
    }
}
