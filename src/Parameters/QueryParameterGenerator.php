<?php

namespace Wilkques\OpenAPI\Parameters;

class QueryParameterGenerator implements ParameterGenerator
{
    use Concerns\GeneratesFromRules;

    protected $rules;

    public function __construct($rules)
    {
        $this->rules = $rules;
    }

    public function getParameters()
    {
        $params = [];
        $arrayTypes = [];

        foreach ($this->rules as $param => $rule) {
            $paramRules = $this->splitRules($rule);
            $enums = $this->getEnumValues($paramRules);
            $type = $this->getParamType($paramRules);

            if ($this->isArrayParameter($param)) {
                $arrayKey = $this->getArrayKey($param);
                $arrayTypes[$arrayKey] = $type;
                continue;
            }

            $paramObj = [
                'in' => $this->getParamLocation(),
                'name' => $param,
                'required' => $this->isParamRequired($paramRules),
                'description' => '',
            ];

            $schema = [
                'type' => $type
            ];

            if (!empty($enums)) {
                $schema = [
                    'type' => $type,
                    'enum' => $enums
                ];
            }

            if ($type === 'array') {
                $schema = [
                    'type' => 'array',
                    'items' => [
                        'type' => 'string'
                    ]
                ];
            }

            $params[$param] = $paramObj + compact('schema');
        }

        $params = $this->addArrayTypes($params, $arrayTypes);

        return ['parameters' => array_values($params)];
    }

    protected function addArrayTypes($params, $arrayTypes)
    {
        foreach ($arrayTypes as $arrayKey => $type) {
            if (!isset($params[$arrayKey])) {
                $params[$arrayKey] = [
                    'in' => $this->getParamLocation(),
                    'name' => $arrayKey,
                    'type' => 'array',
                    'required' => false,
                    'description' => '',
                    'items' => [
                        'type' => $type,
                    ],
                ];
            } else {
                $params[$arrayKey]['type'] = 'array';
                $params[$arrayKey]['items']['type'] = $type;
            }
        }

        return $params;
    }

    public function getParamLocation()
    {
        return 'query';
    }
}
