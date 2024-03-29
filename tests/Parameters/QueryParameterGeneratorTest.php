<?php

namespace Wilkques\OpenAPI\Tests\Parameters;

use Illuminate\Validation\Rule;
use Wilkques\OpenAPI\Parameters\QueryParameterGenerator;
use Wilkques\OpenAPI\Tests\TestCase;
use Wilkques\OpenAPI\Traits\CollectionTrait;

class QueryParameterGeneratorTest extends TestCase
{
    use CollectionTrait;

    public function testRequiredParameter()
    {
        ['parameters' => $queryParameters] = $this->getQueryParameters([
            'id' => 'integer|required',
        ]);

        $this->assertSame('query', $queryParameters[0]['in']);
        $this->assertSame('integer', $queryParameters[0]['schema']['type']);
        $this->assertSame('id', $queryParameters[0]['name']);
        $this->assertSame(true, $queryParameters[0]['required']);
    }

    public function testRulesAsArray()
    {
        ['parameters' => $queryParameters] = $this->getQueryParameters([
            'id' => ['integer', 'required'],
        ]);

        $this->assertSame('query', $queryParameters[0]['in']);
        $this->assertSame('integer', $queryParameters[0]['schema']['type']);
        $this->assertSame('id', $queryParameters[0]['name']);
        $this->assertSame(true, $queryParameters[0]['required']);
    }

    public function testOptionalParameter()
    {
        ['parameters' => $queryParameters] = $this->getQueryParameters([
            'email' => 'email',
        ]);

        $this->assertSame('string', $queryParameters[0]['schema']['type']);
        $this->assertSame('email', $queryParameters[0]['name']);
        $this->assertSame(false, $queryParameters[0]['required']);
    }

    public function testEnumInQuery()
    {
        ['parameters' => $queryParameters] = $this->getQueryParameters([
            'account_type' => 'integer|in:1,2|in_array:foo',
        ]);

        $this->assertSame('integer', $queryParameters[0]['schema']['type']);
        $this->assertSame('account_type', $queryParameters[0]['name']);
        $this->assertSame(['1', '2'], $queryParameters[0]['schema']['enum']);
    }

    public function testEnumRuleObjet()
    {
        ['parameters' => $queryParameters] = $this->getQueryParameters([
            'account_type' => [
                'integer',
                Rule::in(1, 2),
                'in_array:foo',
            ],
        ]);

        $this->assertSame('integer', $queryParameters[0]['schema']['type']);
        $this->assertSame('account_type', $queryParameters[0]['name']);
        $this->assertSame(['1', '2'], $queryParameters[0]['schema']['enum']); //using Rule::in parameters are cast to string
    }

    public function testArrayTypeDefaultsToString()
    {
        ['parameters' => $queryParameters] = $this->getQueryParameters([
            'values' => 'array',
        ]);

        $this->assertSame('array', $queryParameters[0]['schema']['type']);
        $this->assertSame('values', $queryParameters[0]['name']);
        $this->assertSame(['type' => 'string'], $queryParameters[0]['schema']['items']);
        $this->assertSame(false, $queryParameters[0]['required']);
    }

    public function testArrayValidationSyntax()
    {
        ['parameters' => $queryParameters] = $this->getQueryParameters([
            'values.*' => 'integer',
        ]);

        $this->assertSame('array', $queryParameters[0]['schema']['type']);
        $this->assertSame('values', $queryParameters[0]['name']);
        $this->assertSame(['type' => 'integer'], $queryParameters[0]['schema']['items']);
        $this->assertSame(false, $queryParameters[0]['required']);
    }

    public function testArrayValidationSyntaxWithRequiredArray()
    {
        ['parameters' => $queryParameters] = $this->getQueryParameters([
            'values.*'  => 'integer',
            'values'    => 'required',
        ]);

        $this->assertSame('array', $queryParameters[0]['schema']['type']);
        $this->assertSame('values', $queryParameters[0]['name']);
        $this->assertSame(['type' => 'integer'], $queryParameters[0]['schema']['items']);
        $this->assertSame(true, $queryParameters[0]['required']);
    }

    private function getQueryParameters(array $rules)
    {
        return (new QueryParameterGenerator($this->collection($rules)))->getParameters();
    }
}
