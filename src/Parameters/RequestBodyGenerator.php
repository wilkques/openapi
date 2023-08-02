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

    /** @var bool */
    protected $isMime = false;

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
            $this->isMime = true;

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
     * @param string $field
     * 
     * @return string
     */
    protected function propertiesFieldReName(string $field)
    {
        $field = $this->fieldReName($field);

        if (Str::contains($field, '.')) {
            $field = Str::finish($this->fieldReName($field, '.', '['), ']');
        }

        return $field;
    }

    /**
     * @return array
     */
    public function getParameters()
    {
        $request = $this->getDocRequestBody();

        if ($request->has('custom')) {
            // If the value of the custom parameter is "only", then display only the custom parameters.
            // If the value is "merge", then merge the custom parameters with the results of the FormRequest.
            // The default value is "only".
            if (!is_string($request->get('custom')) or !in_array($request->get('custom'), ['only', 'merge'])) {
                throw new \InvalidArgumentException('The value of the custom parameter can only be "only" or "merge".');
            }
        }

        if ('only' === $request->takeOffRecursive('custom', 'only')) {
            return $request->toArray();
        }

        $required = [];

        $properties = [];

        $data = $this->getRules()->transform(function ($rule, $field) {
            // split Rules
            $rule = $this->splitRules($rule);

            // Get whether the property field is required.
            $requiredFieldName = $this->requiredProperties($rule, $field);

            $required = [
                // Check if the property is required.
                'isRequired'    => $this->isPropertyRequired($requiredFieldName),
                // Reorganize the names of the required fields.
                'field'         => $this->required($field),
            ];

            // Generator parameters
            $properties = $this->parameters($field, $rule);

            return compact('required') + $properties;
        });

        if ($data->isNotEmpty()) {
            // get required tag.
            $required = $data->filter(function ($item) {
                return isset($item['required']) && $item['required']['isRequired'];
            })->pluck('required.field')->unique()->values()->toArray();

            // properties remove required key
            $properties = $data->reduce(function ($carry, $item) {
                $carry = $carry ? $carry : [];

                // Remove the required tag.
                unset($item['required']);

                // If the fields are points.*.x and points.*.y, then merge them and remove duplicates.
                return array_merge_distinct_recursive($carry, $item);
            });
        }

        $schema = array_merge([
            'type'  => 'object',
        ], compact('properties'));

        if (!empty($required)) {
            $schema = array_merge($schema, compact('required'));
        }

        // If the controller method includes request.body, merge it with the current properties and remove duplicates.
        return $request->mergeRecursiveDistinct([
            'content' => [
                $this->getContentType() => compact('schema')
            ]
        ])->toArray();
    }

    /**
     * Reorganize the names of the required fields.
     * 
     * @param string $field
     * 
     * @return string
     */
    protected function required($field)
    {
        // if ($this->isMime) {
        //     return $this->propertiesFieldReName($field);
        // }

        return $this->getFirstField($field);
    }
}
