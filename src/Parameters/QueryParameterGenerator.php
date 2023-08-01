<?php

namespace Wilkques\OpenAPI\Parameters;

use Wilkques\OpenAPI\Helpers\Collection;
use Wilkques\OpenAPI\Parameters\Contracts\ParameterGenerator;

class QueryParameterGenerator extends ParameterGenerates implements ParameterGenerator
{
    /** @var Collection */
    protected $rules;

    /** @var Collection */
    protected $docRules;

    /** @var Collection */
    protected $docQuery;

    public function __construct(Collection $rules, Collection $docRules = null, Collection $docQuery = null)
    {
        $this->setRules($rules)->setDocRules($docRules)->setDocQuery($docQuery);
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
     * @param Collection $docRules
     * 
     * @return static
     */
    public function setDocRules(Collection $docRules = null)
    {
        $this->docRules = $docRules ? $docRules : $this->collection();

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
     * @param Collection $docQuery
     * 
     * @return static
     */
    public function setDocQuery(Collection $docQuery = null)
    {
        $this->docQuery = $docQuery ?: $this->collection();

        return $this;
    }

    /**
     * @return Collection
     */
    public function getDocQuery()
    {
        return $this->docQuery;
    }

    /**
     * @param string|null $key
     * 
     * @return array|string
     */
    public function getExample(string $key)
    {
        return $this->getDocRulesByKey($key)['example'] ?? '';
    }

    /**
     * @return array
     */
    public function getParameters()
    {
        $parameters = [];

        $rulesTmp = [];

        foreach ($this->getRules() as $field => $rule) {
            // Retrieve the first field name.
            $firstField = $this->getFirstField($field);

            // To retrieve all field names from the rule array that match a specified field name, such as "abc" and "abc.*".
            $matchFields = $this->matchFields($firstField);

            // Parse the rules.
            $rule = $this->splitRules($rule);

            // Compare the extracted field array to check if it contains a specified field name.
            $matchField = $this->hasField($matchFields, $firstField);

            // Ensure that the extracted field array has a quantity of no more than 1 and does not contain the symbol "." (dot). 
            // Check if the extracted field array contains a specified field name. 
            // Finally, store the result in "rulesTmp", with the first field name as "keyname".
            if ($matchFields->count() > 1 and !$this->hasFields($field, '.') and $matchField) {
                $required = in_array('required', $rule);

                $rulesTmp[$firstField] = compact('required');

                continue;
            }

            // Retrieve the required fields.
            $requiredProperties = $this->requiredProperties($rule, $field);

            // Generate schema.
            $schema = $this->parameters($field, $rule);

            $parameters[$firstField] = array_merge_distinct_recursive([
                'in'            => $this->getParamLocation(),
                'name'          => $firstField,
                'required'      => $this->isPropertyRequired($requiredProperties),
                'schema'        => array_shift($schema),
            ], $parameters[$firstField] ?? [], $this->queryParameters($field));
        }

        if ($rulesTmp) {
            foreach ($rulesTmp as $field => $rule) {
                $parameters[$field] = array_merge($parameters[$field], $rule);
            }
        }

        // If the controller method includes query, merge it with the current properties and remove duplicates.
        if ($docRequestBody = $this->getDocQuery()) {
            $parameters = array_merge_distinct_recursive($docRequestBody->toArray(), $parameters);
        }

        $parameters = array_values($parameters);

        return compact('parameters');
    }

    /**
     * @param string $pattern
     * 
     * @return Collection
     */
    protected function matchFields($pattern)
    {
        return $this->getRules()->filter(function ($value, $key) use ($pattern) {
            return preg_match("/{$pattern}/", $key);
        });
    }

    /**
     * @param Collection $fields
     * @param string $field
     * 
     * @return bool
     */
    protected function hasField($fields, $field)
    {
        return $fields->contains(function ($value, $key) use ($field) {
            return $key === $field;
        });
    }

    /**
     * @param string $field
     * 
     * @return array
     */
    protected function queryParameters($field)
    {
        $hasPointer = $this->hasFields($field, '*', '.');

        $queryParameters = [];

        if ($hasPointer && !str_ends_with($field, '*')) {
            $queryParameters = [
                'explode'   => $hasPointer,
                'style'     => 'deepObject',
            ];
        }

        return $queryParameters;
    }

    /**
     * @return string
     */
    public function getParamLocation()
    {
        return 'query';
    }

    /**
     * @param Collection|array $rule
     * 
     * @return boolean
     */
    protected function isMime($rule)
    {
        return false;
    }
}
