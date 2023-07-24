<?php

namespace Wilkques\OpenAPI\Parameters;

use Illuminate\Support\Str;
use Wilkques\OpenAPI\Helpers\Collection;

class RequestBodyGenerator extends ParameterGenerates
{
    /** @var Collection */
    protected $rules;

    /** @var Collection */
    protected $docRules;

    /** @var Collection */
    protected $docRequestBody;

    /** @var string */
    protected $contentType = 'application/json';

    public function __construct(Collection $rules, Collection $docRules = null, Collection $docRequestBody = null)
    {
        $this->setRules($rules)->setDocRules($docRules)->setDocRequestBody($docRequestBody)->init();
    }

    /**
     * @return static
     */
    public function init()
    {
        if ($this->isMime($this->getRules())) {
            $this->setContentType('multipart/form-data');
        }

        return $this;
    }

    /**
     * @param Collection $rules
     * 
     * @return static
     */
    public function setRules(Collection $rules)
    {
        $this->rules = $rules;

        return $this;
    }

    /**
     * @return Collection
     */
    public function getRules()
    {
        return $this->rules;
    }

    /**
     * @param Collection $rules
     * 
     * @return static
     */
    public function setDocRules(Collection $docRules = null)
    {
        $this->docRules = $docRules ?: $this->collection();

        return $this;
    }

    /**
     * @return Collection
     */
    public function getDocRules()
    {
        return $this->docRules;
    }

    /**
     * @param string $key
     * 
     * @return Collection|mixed
     */
    public function getDocRulesByKey(string $key)
    {
        return $this->getDocRules()->get($key, $this->collection());
    }

    /**
     * @param Collection $rules
     * 
     * @return static
     */
    public function setDocRequestBody(Collection $docRequestBody = null)
    {
        $this->docRequestBody = $docRequestBody ?: $this->collection();

        return $this;
    }

    /**
     * @return Collection
     */
    public function getDocRequestBody()
    {
        return $this->docRequestBody;
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
     * @param Collection|array $rule
     * 
     * @return boolean
     */
    protected function isMime($rule)
    {
        if (!$rule instanceof Collection) {
            $rule = $this->collection($rule);
        }

        return $this->mime($rule)->isNotEmpty();
    }

    /**
     * @param Collection|array $fieldRule
     * 
     * @return Collection
     */
    protected function mime($fieldRule)
    {
        if (!$fieldRule instanceof Collection) {
            $fieldRule = $this->collection($fieldRule);
        }

        return $fieldRule->filter(function ($value) {
            if (is_object($value)) {
                return false;
            }

            if (is_array($value)) {
                return $this->mime($this->collection($value))->isNotEmpty();
            }

            return Str::contains(strtolower($value), ["file", "image", "mimetypes", "mimes"]);
        });
    }

    /**
     * @return array
     */
    public function getParameters()
    {
        $required = [];

        $properties = [];

        $data = $this->getRules()->transform(function ($rule, $field) {
            $rule = $this->splitRules($rule);

            $reFieldName = $this->requiredProperties($rule, $field);

            $required = [
                'isRequired'    => $this->isPropertyRequired($reFieldName),
                'field'         => $reFieldName,
            ];

            $properties = $this->properties($field, $rule);

            return compact('required') + $properties;
        });

        if ($data->isNotEmpty()) {
            // get required tag.
            $required = $data->filter(function ($item) {
                return isset($item['required']) && $item['required']['isRequired'];
            })->keys()->toArray();

            // properties remove required key
            $properties = $data->reduce(function ($carry, $item) {
                $carry = $carry ? $carry : [];

                // Remove the required tag.
                unset($item['required']);

                // If the fields are points.*.x and points.*.y, then merge them and remove duplicates.
                return array_merge_distinct_recursive($carry, $item);
            });
        }

        // If the controller method includes request.body, merge it with the current properties and remove duplicates.
        if ($docRequestBody = $this->getDocRequestBody()) {
            $properties = $docRequestBody->mergeRecursiveDistinct($properties)->toArray();
        }

        $schema = [
            'type'  => 'object',
        ] + compact('properties');

        if (!empty($required)) {
            $schema += compact('required');
        }

        return [
            'content' => [
                $this->getContentType() => compact('schema')
            ]
        ];
    }

    /**
     * @param string $field
     * @param array|Collection $rule
     * 
     * @return array
     */
    protected function properties(string $fields, $rule)
    {
        $segments = explode('.', $fields);

        $result = [];

        $current = &$result;

        $rule = $rule instanceof \WIlkques\OpenAPI\Helpers\Collection ? $rule->toArray() : $rule;

        foreach ($segments as $field) {
            // Next element
            $next = next($segments);

            if ($next === '*' or in_array('array', $rule)) {
                $type = 'array';
            } else if (!$next) {
                $type = $this->getParamType($rule);
            } else {
                $type = 'object';
            }

            $current = $this->getNewPropObj($type, $rule, !$next);

            if ($field !== '*') {
                $current = [$field => $current];

                $current = &$current[$field];
            }

            if ($type === 'array') {
                // If the field name is like abc.*.* or the field name is like abc and the rule is array. 
                // and there is no next element, then the items default type for the element is string.
                if (!$next) {
                    $current['items'] = [
                        'type' => 'string'
                    ];
                }

                $current = &$current['items'];
            } else if ($type === 'object') {
                $current = &$current['properties'];
            }
        }

        // comment doc and merge rules comment
        $current = $this->getDocRulesByKey($fields)->mergeRecursiveDistinct($current)->toArray();

        return $result;
    }
}
