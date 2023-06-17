<?php

namespace Wilkques\OpenAPI\Parameters;

use Wilkques\OpenAPI\Parameters\Contracts\ParameterGenerator;

class QueryParameterGenerator extends ParameterGenerates implements ParameterGenerator
{
    protected $rules;
    /** @var array */
    protected $docRules;

    public function __construct($rules, array $docRules = [])
    {
        $this->rules = $rules;

        $this->setDocRules($docRules);
    }

    /**
     * @param array $docRules
     * 
     * @return static
     */
    public function setDocRules(array $docRules = [])
    {
        $this->docRules = $docRules;

        return $this;
    }

    /**
     * @return array
     */
    public function getDocRules()
    {
        return $this->docRules;
    }

    /**
     * @param string|null $key
     * 
     * @return array|string
     */
    public function getDocRulesByKey(string $key = null)
    {
        return $this->getDocRules()[$key] ?? [];
    }

    /**
     * @param string|null $key
     * 
     * @return array|string
     */
    public function getDescription(string $key)
    {
        return $this->getDocRulesByKey($key)['description'] ?? '';
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

    public function getParameters()
    {
        $params = [];
        $arrayTypes = [];

        foreach ($this->rules as $param => $rule) {
            $paramRules = $this->splitRules($rule);
            $enum = $this->getEnumValues($paramRules);
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
                'description' => $this->getDescription($param),
            ];

            $schema = [
                'type' => $type
            ];

            if (!empty($enum)) {
                $schema += compact('enum');
            }

            $example = $this->getExample($param);

            if ($type === 'array') {
                $items = [
                    'type' => 'string'
                ];

                $schema += compact('items');

              $example  !== '' && $schema['items'] += compact('example');
            } else {
                $example  !== '' && $schema += compact('example');
            }

            $params[$param] = $paramObj + compact('schema');
        }

        $params = $this->addArrayTypes($params, $arrayTypes);

        return ['parameters' => array_values($params)];
    }

    protected function addArrayTypes($params, $arrayTypes)
    {
        foreach ($arrayTypes as $arrayKey => $type) {
            $example = $this->getExample($arrayKey);
            if (!isset($params[$arrayKey])) {
                $params[$arrayKey] = [
                    'in' => $this->getParamLocation(),
                    'name' => $arrayKey,
                    'type' => 'array',
                    'required' => false,
                    'description' => $this->getExample($arrayKey),
                    'items' => [
                        'type' => $type,
                    ],
                ];
            } else {
                $params[$arrayKey]['type'] = 'array';
                $params[$arrayKey]['items']['type'] = $type;
            }

            $example !== '' && $params[$arrayKey]['items'] += compact('example');
        }

        return $params;
    }

    public function getParamLocation()
    {
        return 'query';
    }
}
