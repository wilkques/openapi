<?php

namespace Wilkques\OpenAPI\Tests\Parameters;

use Illuminate\Validation\Rule;
use Wilkques\OpenAPI\Parameters\RequestBodyGenerator;
use Wilkques\OpenAPI\Tests\Stubs\Rules\Uppercase as UppercaseRule;
use Wilkques\OpenAPI\Tests\TestCase;

class RequestBodyGeneratorTest extends TestCase
{
    public function testStructure()
    {
        $requestBody = $this->getBodyParameters([]);

        $this->assertArrayHasKey('content', $requestBody);
        $this->assertArrayHasKey('application/json', $requestBody['content']);
        $this->assertArrayHasKey('schema', $requestBody['content']['application/json']);
        $this->assertArrayHasKey('type', $requestBody['content']['application/json']['schema']);
        $this->assertSame('object', $requestBody['content']['application/json']['schema']['type']);
    }

    public function testRequiredParameters()
    {
        $requestBody = $this->getBodyParameters([
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
        ], $requestBody['content']['multipart/form-data']['schema']['required']);

        return $requestBody;
    }

    /**
     * @depends testRequiredParameters
     */
    public function testDataTypes($requestBody)
    {
        $this->assertEquals([
            'id'            => ['type' => 'integer'],
            'email'         => ['type' => 'string'],
            'address'       => ['type' => 'string'],
            'dob'           => ['type' => 'string'],
            'picture'       => ['type' => 'string', 'format' => 'binary'],
            'is_validated'  => ['type' => 'boolean'],
            'score'         => ['type' => 'number'],
        ], $requestBody['content']['multipart/form-data']['schema']['properties']);
    }

    public function testNoRequiredParameters()
    {
        $requestBody = $this->getBodyParameters([]);

        $this->assertArrayNotHasKey('required', $requestBody['content']['application/json']['schema']);
    }

    public function testEnumInBody()
    {
        $requestBody = $this->getBodyParameters([
            'account_type' => 'integer|in:1,2|in_array:foo',
        ]);

        $this->assertEquals([
            'account_type' => [
                'type' => 'integer',
                'enum' => [1, 2],
            ],
        ], $requestBody['content']['application/json']['schema']['properties']);
    }

    public function testArraySyntax()
    {
        $requestBody = $this->getBodyParameters([
            'matrix' => 'array',
            'matrix.*' => 'array',
            'matrix.*.*' => 'array',
            'matrix.*.*.*' => 'integer',
        ]);

        $this->assertEquals([
            'matrix' => [
                'type' => 'array',
                'items' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'integer',
                        ],
                    ],
                ],
            ],
        ], $requestBody['content']['application/json']['schema']['properties']);

        // $this->assertEquals([
        //     "matrix" => [
        //         "type" => "array",
        //     ],
        //     "matrix[]" => [
        //         "type" => "array",
        //         "items" => [
        //             "type" => "array",
        //         ]
        //     ],
        //     "matrix[][]" => [
        //         "type" => "array",
        //         "items" => [
        //             "type" => "integer",
        //         ]
        //     ]
        // ], $requestBody['content']['application/json']['schema']['properties']);
    }

    public function testObjectInArraySyntax()
    {
        $requestBody = $this->getBodyParameters([
            // 'points' => 'array',
            'points.*.x' => 'numeric',
            'points.*.y' => 'numeric',
        ]);

        $this->assertEquals([
            'points' => [
                'type' => 'array',
                'items' => [
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
        ], $requestBody['content']['application/json']['schema']['properties']);

        // $this->assertEquals([
        //     "points" => [
        //         "type" => "array",
        //     ],
        //     "points[][x]" => [
        //         "type" => "array",
        //         "items" => [
        //             "type" => "number",
        //         ],
        //     ],
        //     "points[][y]" => [
        //         "type" => "array",
        //         "items" => [
        //             "type" => "number",
        //         ],
        //     ],
        // ], $requestBody['content']['application/json']['schema']['properties']);
    }

    public function testSingleObjectSyntax()
    {
        $requestBody = $this->getBodyParameters([
            // 'point' => '',
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
        ], $requestBody['content']['application/json']['schema']['properties']);

        // $this->assertEquals([
        //     "point" => [
        //         "type" => "string",
        //     ],
        //     "point[x]" => [
        //         "type" => "array",
        //         "items" => [
        //             "type" => "number",
        //         ]
        //     ],
        //     "point[y]" => [
        //         "type" => "array",
        //         "items" => [
        //             "type" => "number",
        //         ]
        //     ]
        // ], $requestBody['content']['application/json']['schema']['properties']);
    }

    public function testResolvesRuleEnum()
    {
        $requestBody = $this->getBodyParameters([
            'type' => [
                Rule::in(1, 2, 3),
                'integer',
            ],
        ]);

        $this->assertEquals([
            'type' => [
                'type' => 'integer',
                'enum' => ['1', '2', '3'], //using Rule::in parameters are cast to string
            ],
        ], $requestBody['content']['application/json']['schema']['properties']);
    }

    public function testIgnoresRuleObject()
    {
        $requestBody = $this->getBodyParameters([
            'name' => [
                'string',
                new UppercaseRule,
            ],
        ]);

        $this->assertEquals([
            'name' => [
                'type' => 'string',
            ],
        ], $requestBody['content']['application/json']['schema']['properties']);
    }

    public function testIgnoresClosureRules()
    {
        $requestBody = $this->getBodyParameters([
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
        ], $requestBody['content']['application/json']['schema']['properties']);
    }

    private function getBodyParameters(array $rules)
    {
        $requestBody = (new RequestBodyGenerator($rules))->getParameters();

        return current($requestBody);
    }
}
