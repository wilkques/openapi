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
        if ($this->mimeMethodCheck($this->getRules()))
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
     * @param Generator|null $generator
     * 
     * @return static
     */
    public function setGenerator(Generator $generator = null)
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
            $fieldRule = $this->splitRules($fieldRule);

            $this->addToProperties($field, $fieldRule, $properties);

            $required[] = $this->requiredProperties($fieldRule, $field);
        });

        if (!empty($required)) {
            $required = array_filter($required);

            $requestBody['content'][$this->getContentType()]['schema'] += compact('required');
        }

        $requestBody['content'][$this->getContentType()]['schema'] += compact('properties');

        return compact('requestBody');
    }

    /**
     * @param array $field
     * @param array $fieldRule
     * @param array &$properties
     */
    protected function addToProperties(string $field, array $fieldRule, array &$properties)
    {
        if ($this->getContentType() === 'multipart/form-data') {
            $this->addToPropertiesWithFormData($field, $fieldRule, $properties);
        } else {
            $this->addToPropertiesWithJson(explode('.', $field), $fieldRule, $properties);
        }
    }

    /**
     * @param array $fieldRule
     * @param string $field
     * 
     * @return string|[]
     */
    protected function requiredProperties(array $fieldRule, string $field)
    {
        if ($this->isParamRequired($fieldRule)) {
            if ($this->getContentType() === 'multipart/form-data') {
                $type = $this->getParamType($fieldRule);

                $field = $this->propertiesFieldReName($field, $type);
            }

            return $field;
        }
    }

    /**
     * For Content-Type: Application/json
     * 
     * @param array $field
     * @param array $fieldRule
     * @param array &$properties
     */
    protected function addToPropertiesWithJson(array $fields, array $fieldRule, array &$properties)
    {
        $field = array_shift($fields);

        $type = !empty($fields) ? (current($fields) === '*' ? 'array' : 'object') : $this->getParamType($fieldRule);

        if (!isset($properties[$field])) {
            $properties[$field] = $this->getNewPropObj($type, $fieldRule);
        } else {
            //overwrite previous type in case it wasn't given before
            $properties[$field]['type'] = $type;
        }

        $items = $this->itemsNextCheck($fields, $this->getParamType($fieldRule));

        if (!empty($items)) {
            if (!empty($fields))
                $properties[$field] += $items;
            else
                $properties[$field] = $items;
        }

        if (empty($fields)) {
            return;
        }

        if (current($fields) !== '*') {
            if ($hasItems = $type === 'array') {
                !isset($properties[$field]['items']['properties']) && $properties[$field]['items'] = $this->getNewPropObj('object', $fieldRule);
            }

            $type = 'object';
        }

        if ($type === 'array') {
            $this->addToPropertiesWithJson($fields, $fieldRule, $properties[$field]['items']);
        } elseif ($type === 'object') {
            if (isset($hasItems) && $hasItems) {
                $this->addToPropertiesWithJson($fields, $fieldRule, $properties[$field]['items']['properties']);
            } else {
                $this->addToPropertiesWithJson($fields, $fieldRule, $properties[$field]['properties']);
            }
        }
    }

    /**
     * @param array &$fields
     * @param string $type
     * @param array $items
     * 
     * @return array
     */
    protected function itemsNextCheck(array &$fields, string $type, array $items = [])
    {
        if (current($fields) === '*') {
            array_shift($fields);

            $items = [
                'type'  => 'array',
                'items' => empty($fields) ? compact('type') : $this->itemsNextCheck($fields, $type)
            ];
        }

        return $items;
    }

    /**
     * For Content-Type: multipart/form-data
     * 
     * @param string $field
     * @param array $fieldRule
     * @param array &$properties
     */
    protected function addToPropertiesWithFormData(string $field, array $fieldRule, array &$properties)
    {
        $type = !empty($this->mimeMethodCheck($fieldRule)) ? [
            'type'      => 'string',
            'format'    => 'binary'
        ] : ['type' => $this->getParamType($fieldRule)];

        $enum = $this->getEnumValues($fieldRule);

        !empty($enum) && $type += compact('enum');

        $field = $this->propertiesFieldReName($field, $type['type']);

        if ($type['type'] === 'array') {
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
    }

    /**
     * @param string $type
     * @param array $fieldRule
     * 
     * @return array
     */
    protected function getNewPropObj(string $type, array $fieldRule)
    {
        $propObj = [
            'type' => $type,
        ];

        if ($enums = $this->getEnumValues($fieldRule)) {
            $propObj['enum'] = $enums;
        }

        if ($type === 'array') {
            $propObj['items'] = [];
        } elseif ($type === 'object') {
            $propObj['properties'] = [];
        }

        return $propObj;
    }

    /**
     * @param string $field
     * @param string $type
     * 
     * @return string
     */
    protected function propertiesFieldReName(string $field, string $type)
    {
        $field = $this->fieldReName($field);

        $type === 'array' && $field = Str::finish($field, '[');

        if ($type === 'array' || Str::contains($field, '.'))
            $field = Str::finish($this->fieldReName($field, '.', '['), ']');

        return $field;
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
            if (is_object($value)) return;
            if (is_array($value)) return $this->mime($value);
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
