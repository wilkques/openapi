<?php

namespace Wilkques\OpenAPI\Tests\Parameters;

use Illuminate\Validation\Rule;
use Wilkques\OpenAPI\Parameters\BodyParameterGenerator;
use Wilkques\OpenAPI\Tests\Stubs\Rules\Uppercase as UppercaseRule;
use Wilkques\OpenAPI\Tests\TestCase;

class BodyParameterGeneratorTest extends TestCase
{
    public function testStructure()
    {
        $bodyParameters = $this->getBodyParameters([]);

        $this->assertArrayHasKey('in', $bodyParameters);
        $this->assertArrayHasKey('name', $bodyParameters);
        $this->assertArrayHasKey('schema', $bodyParameters);
        $this->assertArrayHasKey('type', $bodyParameters['schema']);
        $this->assertSame('object', $bodyParameters['schema']['type']);
    }

    public function testRequiredParameters()
    {
        $bodyParameters = $this->getBodyParameters([
            'id'            => 'integer|required',
            'email'         => 'email|required',
            'address'       => 'string|required',
            'dob'           => 'date|required',
            'picture'       => 'file',
            'is_validated'  => 'boolean',
            'score'         => 'numeric',
        ]);

        $this->assertEquals([
            'id',
            'email',
            'address',
            'dob',
        ], $bodyParameters['schema']['required']);

        return $bodyParameters;
    }

    /**
     * @depends testRequiredParameters
     */
    public function testDataTypes($bodyParameters)
    {
        $this->assertEquals([
            'id'            => ['type' => 'integer'],
            'email'         => ['type' => 'string'],
            'address'       => ['type' => 'string'],
            'dob'           => ['type' => 'string'],
            'picture'       => ['type' => 'string'],
            'is_validated'  => ['type' => 'boolean'],
            'score'         => ['type' => 'number'],
        ], $bodyParameters['schema']['properties']);
    }

    public function testNoRequiredParameters()
    {
        $bodyParameters = $this->getBodyParameters([]);

        $this->assertArrayNotHasKey('required', $bodyParameters['schema']);
    }

    public function testEnumInBody()
    {
        $bodyParameters = $this->getBodyParameters([
            'account_type' => 'integer|in:1,2|in_array:foo',
        ]);

        $this->assertEquals([
            'account_type' => [
                'type' => 'integer',
                'enum' => [1, 2],
            ],
        ], $bodyParameters['schema']['properties']);
    }

    public function testArraySyntax()
    {
        $bodyParameters = $this->getBodyParameters([
            'matrix' => 'array',
            'matrix.*' => 'array',
            'matrix.*.*' => 'integer',
        ]);

        $this->assertEquals([
            'matrix' => [
                'type' => 'array',
                'items' => [
                    [
                        'type' => 'array',
                        'items' => [
                            [
                                'type' => 'integer',
                            ],
                        ],
                    ],
                ],
            ],
        ], $bodyParameters['schema']['properties']);
    }

    public function testObjectInArraySyntax()
    {
        $bodyParameters = $this->getBodyParameters([
            'points' => 'array',
            'points.*.x' => 'numeric',
            'points.*.y' => 'numeric',
        ]);

        $this->assertEquals([
            'points' => [
                'type' => 'array',
                'items' => [
                    [
                        'type' => 'object',
                        'properties' => [
                            'x' => [
                                'type' => 'number',
                            ],
                            'y' => [
                                'type' => 'number',
                            ],
                        ],
                    ],
                ],
            ],
        ], $bodyParameters['schema']['properties']);
    }

    public function testSingleObjectSyntax()
    {
        $bodyParameters = $this->getBodyParameters([
            'point' => '',
            'point.x' => 'numeric',
            'point.y' => 'numeric',
        ]);

        $this->assertEquals([
            'point' => [
                'type' => 'object',
                'properties' => [
                    'x' => [
                        'type' => 'number',
                    ],
                    'y' => [
                        'type' => 'number',
                    ],
                ],
            ],
        ], $bodyParameters['schema']['properties']);
    }

    public function testResolvesRuleEnum()
    {
        $bodyParameters = $this->getBodyParameters([
            'type' => [
                Rule::in(1, 2, 3),
                'integer',
            ],
        ]);

        $this->assertEquals([
            'type' => [
                'type' => 'integer',
                'enum' => ['"1"', '"2"', '"3"'], //using Rule::in parameters are cast to string
            ],
        ], $bodyParameters['schema']['properties']);
    }

    public function testIgnoresRuleObject()
    {
        $bodyParameters = $this->getBodyParameters([
            'name' => [
                'string',
                new UppercaseRule,
            ],
        ]);

        $this->assertEquals([
            'name' => [
                'type' => 'string',
            ],
        ], $bodyParameters['schema']['properties']);
    }

    public function testIgnoresClosureRules()
    {
        $bodyParameters = $this->getBodyParameters([
            'name' => [
                'string',
                function ($attribute, $value, $fail) {
                    if ($value === 'foo') {
                        $fail($attribute . ' is invalid.');
                    }
                },
            ],
        ]);

        $this->assertEquals([
            'name' => [
                'type' => 'string',
            ],
        ], $bodyParameters['schema']['properties']);
    }

    private function getBodyParameters(array $rules)
    {
        $bodyParameters = (new BodyParameterGenerator($rules))->getParameters();

        return current($bodyParameters);
    }
}
