<?php

namespace Wilkques\OpenAPI\Parameters;

use Illuminate\Support\Str;
use Wilkques\OpenAPI\Helpers\Collection;
use Wilkques\OpenAPI\Traits\CollectionTrait;

abstract class ParameterGenerates
{
    use CollectionTrait;

    /**
     * @param string|array $rules
     * 
     * @return array
     */
    protected function splitRules($rules)
    {
        if (is_string($rules)) {
            return explode('|', $rules);
        }

        return $rules;
    }

    /**
     * @param Collection|array $rule
     * 
     * @return string
     */
    protected function getParamType($rule)
    {
        if (!$rule instanceof Collection) {
            $rule = $this->collection($rule);
        }

        if ($rule->contains('integer')) {
            return 'integer';
        } else if ($rule->contains('numeric')) {
            return 'number';
        } else if ($rule->contains('boolean')) {
            return 'boolean';
        } else if ($rule->contains('array')) {
            return 'array';
        } else {
            //date, ip, email, etc..
            return 'string';
        }
    }

    /**
     * @param Collection|array $rule
     * 
     * @return bool
     */
    protected function isParamRequired($rule)
    {
        if (!$rule instanceof Collection) {
            $rule = $this->collection($rule);
        }

        return $rule->contains('required');
    }

    /**
     * @param string $param
     * 
     * @return bool
     */
    protected function hasFields($haystack, ...$needles)
    {
        return Str::contains($haystack, $needles);
    }

    /**
     * @param string $pattern
     * 
     * @return array
     */
    protected function getPregFields($pattern)
    {
        preg_match_all('/[\w]+/', $pattern, $matches);

        return array_pop($matches);
    }

    /**
     * @param string $field
     * 
     * @return string
     */
    protected function getLastField($field)
    {
        $field = $this->getPregFields($field);

        return end($field);
    }

    /**
     * @param string $field
     * 
     * @return string
     */
    protected function getFirstField($field)
    {
        $field = $this->getPregFields($field);

        return current($field);
    }

    /**
     * @param array $paramRules
     * 
     * @return string|false
     */
    protected function getEnumValues($paramRules)
    {
        $in = $this->getInParameter($paramRules);

        if (!$in) {
            return [];
        }

        [$param, $vals] = explode(':', $in);

        return explode(',', preg_replace('/"/i', '', $vals));
    }

    /**
     * @param array $paramRules
     * 
     * @return string|false
     */
    private function getInParameter($paramRules)
    {
        foreach ($paramRules as $rule) {
            if ((is_string($rule) || method_exists($rule, '__toString')) && Str::startsWith($rule, 'in:')) {
                return $rule;
            }
        }

        return false;
    }

    /**
     * @param Collection|array $rule
     * @param string $field
     * 
     * @return string|null
     */
    protected function requiredProperties($rule, string $field)
    {
        if ($this->isParamRequired($rule)) {
            return $this->getPregFields($field);
        }

        return null;
    }

    /**
     * @param string $field
     * 
     * @return bool
     */
    protected function isPropertyRequired($field)
    {
        if (!$field) {
            return false;
        }

        return true;
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

    /**
     * @param string $field
     * @param array|Collection $rule
     * 
     * @return array
     */
    protected function parameters(string $fields, $rule)
    {
        $segments = explode('.', $fields);

        $result = [];

        $current = &$result;

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

        // Merge arrays with comment parsing.
        $current = $this->getDocRulesByKey($fields)->mergeRecursiveDistinct($current)->toArray();

        return $result;
    }

    /**
     * @param string $type
     * @param array|Collection $rule
     * @param bool $isEnd
     * 
     * @return array
     */
    protected function getNewPropObj(string $type, $rule, bool $isEnd = false)
    {
        $propObj = [
            'type' => $type,
        ];

        if ($enums = $this->getEnumValues($rule)) {
            $propObj['enum'] = $enums;
        }

        if ($isEnd && $this->isMime($rule)) {
            $propObj = array_merge($propObj, [
                'format'    => 'binary',
            ]);
        }

        switch ($type) {
            case 'array':
                $propObj['items'] = [];
                break;
            case 'object':
                $propObj['properties'] = [];
                break;
        }

        return $propObj;
    }

    /**
     * @param Collection|array $rule
     * 
     * @return boolean
     */
    abstract protected function isMime($rule);

    /**
     * @param string $key
     * 
     * @return Collection
     */
    abstract public function getDocRulesByKey(string $key);
}
