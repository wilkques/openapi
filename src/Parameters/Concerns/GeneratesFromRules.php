<?php

namespace Wilkques\OpenAPI\Parameters\Concerns;

use Illuminate\Support\Str;

trait GeneratesFromRules
{
    /**
     * @param string|array $rules
     * 
     * @return array
     */
    protected function splitRules($rules)
    {
        if (is_string($rules)) {
            return explode('|', $rules);
        } else {
            return $rules;
        }
    }

    protected function getParamType(array $paramRules)
    {
        if (in_array('integer', $paramRules)) {
            return 'integer';
        } elseif (in_array('numeric', $paramRules)) {
            return 'number';
        } elseif (in_array('boolean', $paramRules)) {
            return 'boolean';
        } elseif (in_array('array', $paramRules)) {
            return 'array';
        } else {
            //date, ip, email, etc..
            return 'string';
        }
    }

    protected function isParamRequired(array $paramRules)
    {
        return in_array('required', $paramRules);
    }

    protected function isArrayParameter($param)
    {
        return Str::contains($param, '*');
    }

    protected function getArrayKey($param)
    {
        return current(explode('.', $param));
    }

    protected function getEnumValues(array $paramRules)
    {
        $in = $this->getInParameter($paramRules);

        if (!$in) {
            return [];
        }

        [$param, $vals] = explode(':', $in);

        return explode(',', preg_replace('/"/i', '', $vals));
    }

    private function getInParameter(array $paramRules)
    {
        foreach ($paramRules as $rule) {
            if ((is_string($rule) || method_exists($rule, '__toString')) && Str::startsWith($rule, 'in:')) {
                return $rule;
            }
        }

        return false;
    }
}
