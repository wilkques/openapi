<?php

namespace Wilkques\OpenAPI\Parameters;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Wilkques\OpenAPI\Generator;

class RequestBodyGenerator
{
    use Concerns\GeneratesFromRules;
    /** @var array */
    protected $rules;
    /** @var string */
    protected $contentType = 'application/json';
    /** @var Generatoer */
    protected $generator;

    public function __construct(array $rules, Generator $generator = null)
    {
        $this->setRules($rules)->setGenerator($generator)->init();
    }

    /**
     * @return static
     */
    public function init()
    {
        if (strtolower($this->getGenerator()->getMethod()) === 'post')
            $this->setContentType('multipart/form-data');

        return $this;
    }

    /**
     * @param array $rules
     * 
     * @return static
     */
    public function setRules(array $rules)
    {
        $this->rules = $rules;

        return $this;
    }

    /**
     * @return array
     */
    public function getRules()
    {
        return $this->rules;
    }

    /**
     * @param Generator $generator
     * 
     * @return static
     */
    public function setGenerator(Generator $generator)
    {
        $this->generator = $generator;

        return $this;
    }

    /**
     * @return Generator
     */
    public function getGenerator()
    {
        return $this->generator;
    }

    /**
     * @param string $contentType
     * 
     * @return static
     */
    public function setContentType(string $contentType = 'application/json')
    {
        $this->contentType = $contentType;

        return $this;
    }

    /**
     * @return string
     */
    public function getContentType()
    {
        return $this->contentType;
    }

    /**
     * @return array
     */
    public function getParameters()
    {
        $required = [];
        $properties = [];

        $requestBody = [
            'content' => [
                $this->getContentType() => [
                    'schema' => [
                        'type' => 'object',
                    ],
                ]
            ]
        ];

        collect($this->getRules())->map(function ($fieldRule, $field) use (&$properties, &$required) {
            $fieldCheck = Str::contains($field, '.');

            $field = $this->fieldReName($field);

            // $field = Str::of($field)->matchAll('/(\b((?!\.\*|\.\*\.|\*\.)\w)+\b)/');

            $fieldRule = $this->splitRules($fieldRule);

            if (!empty($this->mimeMethodCheck($fieldRule))) {
                $type = [
                    'type'      => 'string',
                    'format'    => 'binary'
                ];
            } else {
                $type = ['type' => $this->getParamType($fieldRule)];
            }

            if ($fieldCheck) {
                $field = Str::finish($this->fieldReName($field, '.', '['), ']');

                $properties += [
                    $field => [
                        'type'  => 'array',
                        'items' => $type
                    ]
                ];
            } else {
                $properties += [
                    $field => $type
                ];
            }

            if ($this->isParamRequired($fieldRule)) {
                $required[] = $field;
            }
        });

        if (!empty($required)) {
            $requestBody['content'][$this->contentType]['schema'] += compact('required');
        }

        $requestBody['content'][$this->contentType]['schema'] += compact('properties');

        return compact('requestBody');
    }

    /**
     * @param array $fieldRule
     * 
     * @return boolean
     */
    protected function mimeMethodCheck(array $fieldRule)
    {
        $fieldRule = $this->mime($fieldRule);

        if (!empty($fieldRule)) {
            return true;
        }

        return false;
    }

    /**
     * @param array $fieldRule
     * 
     * @return array
     */
    protected function mime(array $fieldRule)
    {
        return Arr::where($fieldRule, function ($value) {
            return Str::contains(strtolower($value), ["file", "image", "mimetypes", "mimes"]);
        });
    }

    /**
     * @param array $fields
     * @param string $needles
     * @param string $replace
     * 
     * @return array
     */
    protected function fieldsReName(array $fields, string $needles = ".*", string $replace = '[]')
    {
        return collect($fields)->transform(
            fn ($fieldName) => $this->fieldReName($fieldName, $needles, $replace)
        )->toArray();
    }

    /**
     * @param string $fieldName
     * @param string $needles
     * @param string $replace
     * 
     * @return string
     */
    protected function fieldReName(string $fieldName, string $needles = ".*", string $replace = '[]')
    {
        if (Str::contains(strtolower($fieldName), $needles)) {
            $fieldName = (string) Str::of($fieldName)->replace($needles, $replace);
        }

        return $fieldName;
    }
}
