<?php

namespace Wilkques\OpenAPI;

use Illuminate\Config\Repository as Config;
use Wilkques\OpenAPI\DataObjects\Routes;
use Wilkques\OpenAPI\Helpers\Collection;
use Wilkques\OpenAPI\Parameters\PathParameterGenerator;
use Wilkques\OpenAPI\Parameters\QueryParameterGenerator;
use Wilkques\OpenAPI\Parameters\RequestBodyGenerator;
use Wilkques\OpenAPI\Traits\CollectionTrait;

class Generator
{
    use CollectionTrait;

    /** @var \Wilkques\OpenAPI\DataObjects\Routes */
    protected $routes;

    /** @var Config */
    protected $config;

    /** @var Collection */
    protected $collection;

    /** @var string */
    protected $configPrefix = 'openapi';

    /**
     * @param Routes $routes
     * @param Config $config
     */
    public function __construct(Routes $routes, Config $config)
    {
        $this->routes = $routes;
        $this->config = $config;
    }

    /**
     * @return array
     */
    public function handle()
    {
        return $this->generate();
    }

    /**
     * @param array $collection
     * 
     * @return static
     */
    public function setCollection($collection)
    {
        return $this->collection = $collection;
    }

    /**
     * @return array
     */
    public function getCollection()
    {
        return $this->collection;
    }

    public function getConfig($key = null)
    {
        if (!$key) {
            return $this->config->get("{$this->configPrefix}");
        }

        return $this->config->get("{$this->configPrefix}.{$key}");
    }

    /**
     * @return array
     */
    protected function generate()
    {
        $generate = $this->routes->handle()->items()->transform(
            fn (Collection $item) => $item->transform(
                fn (Collection $item, $index) => $this->generateRoutes($item, $index)
            )->reduce(fn ($item, $index) => $this->reduce($item, $index))
        )->reduce(fn ($item, $index) => $this->reduce($item, $index));

        $generate = $generate ?: [
            'paths' => []
        ];

        // config openapi.basic
        $basic = $this->getConfig('basic');

        // get components
        $components = $this->components();

        return array_merge_recursive($basic, compact('components'), $generate);
    }

    /**
     * @param Collection $item
     * @param string $index
     * 
     * @return array
     */
    protected function generateRoutes(Collection $item, $index)
    {
        // servers tag
        if ($index === 'servers' && $item->isNotEmpty()) {
            return [
                'servers' => $item->toArray()
            ];
        }

        // deprecated tag
        $deprecated = $item->get('deprecated', false);

        // http method tag
        $method = $item->get('method');

        // request tag
        $request = $item->get('request', $this->collection());

        // response tag
        $response = $this->responses($item->get('response', []));

        // uri path
        $parameters = (new PathParameterGenerator($item->get('originUri'), $item->get('path', [])))->getParameters();

        // request path
        if ($request->has('parameters')) {
            $parameters = array_merge_recursive($request->get('parameters'), $parameters);
        }

        // has request rules
        $rules = $item->get('rules', $this->collection());

        $parameterGenerator = $this->getParameterGenerator($method, $rules, $request);

        // check is get query or post requestBody
        if ($parameterGenerator instanceof QueryParameterGenerator) {
            $parameters = array_merge_recursive($parameters, $parameterGenerator->getParameters());
        } else {
            $requestBody = $parameterGenerator->getParameters();
        }

        // security tag
        $security = $item->get('security', $this->collection())
            ->mergeRecursiveDistinct(
                $request->get(
                    'security',
                    $this->collection()
                )
            )->toArray();

        return [
            'paths' => [
                $item->get('uri') => [
                    $method => [
                        'summary' => $item->get('summary'),
                        'description' => $item->get('description'),
                        'tags' => $request->get('tags', $this->collection())->toArray(),
                        'requestBody' => $requestBody ?? [],
                        'responses' => $response->toArray()
                    ] + $parameters + compact('deprecated', 'security')
                ]
            ]
        ];
    }

    /**
     * @param string $method
     * @param \Illuminate\Support\Collection $rules
     * @param \Illuminate\Support\Collection $requestDoc
     * 
     * @return RequestBodyGenerator|QueryParameterGenerator
     */
    protected function getParameterGenerator($method, $rules, $requestDoc)
    {
        $rule = $rules->get('rules', $this->collection());

        $fields = $rules->get('fields', $this->collection());

        if (in_array($method, ['post', 'put', 'patch'])) {
            return new RequestBodyGenerator($rule, $fields, $requestDoc->get('requestBody'));
        }

        return new QueryParameterGenerator($rule, $fields, $requestDoc->get('parameters'));
    }

    /**
     * @param string|int $httpCode
     * 
     * @return string
     */
    protected function getResopnseDescription($httpCode = 200)
    {
        return \Illuminate\Http\Response::$statusTexts[$httpCode];
    }

    /**
     * @param array|null $carray
     * @param array $item
     * 
     * @return array
     */
    protected function reduce($carry, $item)
    {
        $carry = $carry ?? [];

        return array_merge_distinct_recursive($carry, $item);
    }

    /**
     * @param array|[] $component
     * 
     * @return array
     */
    protected function components($component = [])
    {
        // config.openapi.components
        $components = $this->getConfig("components");

        // config.openapi.responseDefault if false. remove response default
        if (!$this->getConfig('responseDefault', true)) {
            if (array_key_exists('schemas', $components)) {
                $components['schemas'] = array_diff_key($components['schemas'], array_flip(['defaultSuccess', 'defaultError']));
            }
        }

        // merge and filter
        return array_filter(array_merge($components, $component));
    }

    /**
     * @param Collection|[] $response
     * 
     * @return Collection
     */
    protected function responses($response = [])
    {
        // init
        $responses = $this->collection();

        // if config.openapi.responseDefault is true, generate default response
        if ($this->getConfig('responseDefault', true)) {
            // default response 200
            $responses = $responses->mergeRecursiveDistinct(
                $this->response(200, $this->whenConfigContent($this->getConfig("components.schemas.defaultSuccess"), 'defaultSuccess'))
            );

            // default response 400
            $responses = $responses->mergeRecursiveDistinct(
                $this->response(400, $this->whenConfigContent($this->getConfig("components.schemas.defaultError"), 'defaultError'))
            );

            // default response 500
            $responses = $responses->mergeRecursiveDistinct(
                $this->response(500, $this->whenConfigContent($this->getConfig("components.schemas.defaultError"), 'defaultError'))
            );
        }

        return $responses->mergeRecursiveDistinct($response);
    }

    /**
     * @param int $httpCode
     * @param array|[] $content
     * 
     * @return array
     */
    private function response($httpCode = 200, $content = [])
    {
        return [
            $httpCode => [
                'description'                       => $this->getResopnseDescription(),
                'content'                           => [
                    'application/json'              => [
                        'schema'                    => $content,
                    ],
                ],
            ],
        ];
    }

    /**
     * @param mixed $schemas
     * @param string $model
     * 
     * @return array
     */
    public function whenConfigContent($schemas, $model)
    {
        if (!$schemas) {
            return [
                'type'                  => 'object',
                'properties'            => [
                    'message'           => [
                        'type'          => 'string',
                        'description'   => 'default message'
                    ],
                ],
            ];
        }

        return [
            '$ref' => "#/components/schemas/{$model}"
        ];
    }
}
