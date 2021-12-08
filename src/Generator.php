<?php

namespace Wilkques\OpenAPI;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use phpDocumentor\Reflection\DocBlock;
use phpDocumentor\Reflection\DocBlock\Tags\Generic;
use phpDocumentor\Reflection\DocBlockFactory;
use ReflectionClass;
use ReflectionMethod;
use Wilkques\OpenAPI\DataObjects\Route;

class Generator
{
    /** @var string */
    const OAUTH_TOKEN_PATH = '/oauth/token';
    /** @var string */
    const OAUTH_AUTHORIZE_PATH = '/oauth/authorize';
    /** @var string */
    const OAUTH_REFRESH_PATH = '/token/refresh';

    /** @var array */
    protected $config;
    /** @var string|null */
    protected $routeFilter;
    /** @var string|null */
    protected $routeExcept;
    /** @var array */
    protected $docs;
    /** @var Route */
    protected $route;
    /** @var string */
    protected $method;
    /** @var \phpDocumentor\Reflection\DocBlockFactory */
    protected $docParser;
    /** @var boolean */
    protected $hasSecurityDefinitions;
    /** @var string */
    protected $contentType = "application/json";

    public function __construct(array $config, string $routeFilter = null)
    {
        $this->setConfig($config)->setRouteFilter($routeFilter);
        $this->docParser = DocBlockFactory::createInstance();
        $this->hasSecurityDefinitions = false;
    }

    /**
     * @param array $config
     * 
     * @return static
     */
    public function setConfig(array $config)
    {
        $this->config = $config;

        return $this;
    }

    /**
     * @param string|null $routeFilter
     * 
     * @return static
     */
    public function setRouteFilter(string $routeFilter = null)
    {
        $this->routeFilter = $routeFilter;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getRouteFilter()
    {
        return $this->routeFilter;
    }

    /**
     * @param string|null $routeExcept
     * 
     * @return static
     */
    public function setExceptRoute(string $routeExcept = null)
    {
        $this->routeExcept = $routeExcept;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getExceptRoute()
    {
        return $this->routeExcept;
    }

    /**
     * @return array
     */
    public function generate()
    {
        $this->docs = $this->config['baseic'];

        $this->parseSecurity();

        collect($this->getAppRoutes())->map(
            fn ($route) => $this->appRoutesMap(
                $route,
                fn ($methods) => collect($methods)->map(
                    fn ($method) => $this->appRoutesMethodsMap($method)
                )
            )
        );

        $this->docsDataHandle();

        return $this->docs;
    }

    /**
     * @return static
     */
    private function docsDataHandle()
    {
        !array_key_exists('paths', $this->docs) && $this->docs += ['paths' => []];

        isset($this->docs['servers']) && $this->docs['servers'] = array_unique($this->docs['servers'], SORT_REGULAR);

        $this->docs['paths'] = collect($this->docs['paths'])->filter()->toArray();

        return $this;
    }

    /**
     * @param Route $route
     * @param Closure $closure
     * 
     * @return Route
     */
    protected function appRoutesMap(Route $route, Closure $closure)
    {
        $this->route = $route;

        if ($this->routeFilterAndExcept()) return;

        $actionClassInstance = $this->getActionClassInstance();

        $docBlock = $actionClassInstance ? ($actionClassInstance->getDocComment() ?: '') : '';

        $this->parseActionDocBlock($docBlock);

        $closure($route->methods());

        return $route;
    }

    /**
     * @return boolean
     */
    private function routeFilterAndExcept()
    {
        return $this->routeOnlyNamespace() ||
            $this->routeExcept() ||
            $this->getRouteFilter() && $this->isFilteredRoute() ||
            $this->getExceptRoute() && $this->isExceptRoute();
    }

    /**
     * @return boolean
     */
    private function routeOnlyNamespace()
    {
        return !empty($this->config['only']['namespace']) &&
            !Str::containsAll(
                Str::start($this->route->getActionNamespace(), "\\"),
                array_map(
                    fn ($item) => Str::start($item, '\\'),
                    $this->config['only']['namespace']
                )
            );
    }

    /**
     * @return boolean
     */
    private function routeExcept()
    {
        return !empty($this->config['except']['routes']) &&
            $this->routeExceptUri($this->route->uri()) ||
            $this->routeExceptAsName($this->route->getAs());
    }

    /**
     * @param string $uri
     * 
     * @return boolean
     */
    private function routeExceptUri(string $uri)
    {
        return in_array(
            Str::start($uri, "/"),
            array_map(
                fn ($item) => Str::start($item, '/'),
                $this->config['except']['routes']['uri']
            )
        );
    }

    /**
     * @param string|null $name
     * 
     * @return boolean
     */
    private function routeExceptAsName(string $name = null)
    {
        return is_null($name) ? false : in_array($name, $this->config['except']['routes']['name']);
    }

    /**
     * @param string $parsedComment
     * 
     * @return static
     */
    private function parseActionDocBlock(string $parsedComment)
    {
        if (!$parsedComment) return $this;

        $docBlock = $this->docParser->create($parsedComment);

        $servers = $this->servers($docBlock);

        !empty($servers) && $this->docs = array_merge_recursive($this->docs, compact('servers'));

        return $this;
    }

    /**
     * @param DocBlock $docBlock
     * @param array $requestBdoy
     * 
     * @return array
     */
    protected function servers(DocBlock $docBlock, array $servers = [])
    {
        $serversDoc = $this->getDocsTagsByName($docBlock, 'server');

        !empty($serversDoc) && $servers = collect($serversDoc)->map(fn ($server) => $this->docsBody($server))->pop();

        return $servers;
    }

    /**
     * @param string $method
     * 
     * @return string
     */
    protected function appRoutesMethodsMap(string $method)
    {
        $this->setMethod($method);

        if (in_array($this->getMethod(), $this->config['ignoredMethods'])) {
            return;
        }

        $this->generatePath();

        return $method;
    }

    /**
     * @return static
     */
    protected function parseSecurity()
    {
        if (!empty($this->config['securityDefinitions']['securitySchemes'])) {
            $this->docs['components']['securitySchemes'] = $this->generateSecurityDefinitions();
            $this->hasSecurityDefinitions = true;
        }

        return $this;
    }

    /**
     * @return array
     */
    protected function getAppRoutes()
    {
        return array_map(function ($route) {
            return new DataObjects\Route($route);
        }, app('router')->getRoutes()->getRoutes());
    }

    /**
     * @return array
     */
    protected function generateSecurityDefinitions()
    {
        return collect(
            $this->config['securityDefinitions']['securitySchemes']
        )->transform(
            fn ($item, $key) => $this->securityTransfrom($item, $key)
        )->toArray();
    }

    /**
     * @param array $item
     * @param string $key
     * 
     * @return array
     */
    protected function securityTransfrom(array $item = null, string $key = null)
    {
        if ($item['type'] === 'apiKey') {
            $this->securitySchemesKeysCheck($item, [
                'type', 'in', 'name', 'description'
            ], $key);
        }

        if ($item['type'] === 'oauth2') {
            $scopes = $this->generateOauthScopes();

            if (isset($item['flow'])) {
                return $this->oauth2SecuritySchemes($item, $scopes);
            }

            if (isset($item['flows'])) {
                $this->passportSecuritySchemes($item, $scopes, $key);
            }
        }

        return $item;
    }

    /**
     * @param array $item
     * @param array $scopes
     * 
     * @return array
     */
    private function oauth2SecuritySchemes(array $item, array $scopes)
    {
        $authFlow = $item['flow'];

        $this->validateAuthFlow($authFlow);

        $flowData = $this->setAuthorizationUrlEndpotin($item, $authFlow);

        $flowData = $this->setTokenUrlEndpotin($item, $authFlow, $flowData);

        return [
            'type' => $item['type'],
            'description' => $item['description'] ?? '',
            'flows' => [
                $authFlow => $this->setScopes($item, $scopes, $flowData)
            ]
        ];
    }

    /**
     * @param array $item
     * @param array $scopes
     * @param array $flowData
     * 
     * @return array
     */
    private function setScopes(array $item, array $scopes, array $flowData)
    {
        $scopes = empty($this->scopes($item)) ? $scopes : $item['scopes'];

        return compact('scopes') + $flowData;
    }

    /**
     * @param array $item
     * @param string $authFlow
     * @param array $flowData
     * 
     * @return array
     */
    private function setAuthorizationUrlEndpotin(array $item, string $authFlow, array $flowData = [])
    {
        $this->checkAuthorizationUrlEndpotin($item, $authFlow) &&
            $flowData['authorizationUrl'] = $this->getEndpoint(self::OAUTH_AUTHORIZE_PATH);

        return $flowData;
    }

    /**
     * @param array $item
     * @param string $authFlow
     * 
     * @return boolean
     */
    private function checkAuthorizationUrlEndpotin(array $item, string $authFlow)
    {
        return $this->authorizationUrl($item) == '' && in_array($authFlow, ['implicit', 'accessCode']);
    }

    /**
     * @param array $item
     * @param string $authFlow
     * @param array $flowData
     * 
     * @return array
     */
    private function setTokenUrlEndpotin(array $item, string $authFlow, array $flowData = [])
    {
        $this->checkTokenUrlEndpotin($item, $authFlow) &&
            $flowData['tokenUrl'] = $this->getEndpoint(self::OAUTH_TOKEN_PATH);

        return $flowData;
    }

    /**
     * @param array $item
     * @param string $authFlow
     * 
     * @return boolean
     */
    private function checkTokenUrlEndpotin(array $item, string $authFlow)
    {
        return $this->tokenUrl($item) == '' && in_array($authFlow, ['password', 'application', 'accessCode']);
    }

    /**
     * @param array $item
     * @param array $scopes
     * @param string $key
     * 
     * @return array
     */
    private function passportSecuritySchemes($item, $scopes, $key)
    {
        $this->securitySchemesKeysCheck($item, [
            'type', 'in', 'scheme', 'flows', 'description'
        ], $key);

        $authorizationUrl = $this->getEndpoint(self::OAUTH_AUTHORIZE_PATH);

        $tokenUrl = $this->getEndpoint(self::OAUTH_TOKEN_PATH);

        $refreshUrl = $this->getEndpoint(self::OAUTH_REFRESH_PATH);

        $item['flows']['authorizationCode'] = compact('authorizationUrl', 'tokenUrl', 'refreshUrl', 'scopes');

        return $item;
    }

    /**
     * @param array $item
     * @param array $checkAry
     * @param string $key
     * @param array $exceptAry
     * 
     * @throws OpenAPIException
     * 
     * @return static
     */
    private function securitySchemesKeysCheck($item, $checkAry, $key, array $exceptAry = ['description'])
    {
        if (array_diff((array_keys($item) + $exceptAry), $checkAry)) {
            throw new OpenAPIException("Check $key format");
        }

        return $this;
    }

    /**
     * @param array $item
     * 
     * @throws OpenAPIException
     * 
     * @return string
     */
    private function authorizationUrl(array $item)
    {
        if (isset($item['authorizationUrl']) && !is_string($item['authorizationUrl']))
            throw new OpenAPIException("authorizationUrl must be string type");

        return $item['authorizationUrl'] ?? '';
    }

    /**
     * @param array $item
     * 
     * @throws OpenAPIException
     * 
     * @return string
     */
    private function tokenUrl(array $item)
    {
        if (isset($item['tokenUrl']) && !is_string($item['tokenUrl']))
            throw new OpenAPIException("tokenUrl must be string type");

        return $item['tokenUrl'] ?? '';
    }

    /**
     * @param array $item
     * 
     * @throws OpenAPIException
     * 
     * @return string
     */
    private function refreshUrl(array $item)
    {
        if (isset($item['refreshUrl']) && !is_string($item['refreshUrl']))
            throw new OpenAPIException("refreshUrl must be string type");

        return $item['refreshUrl'] ?? '';
    }

    /**
     * @param array $item
     * 
     * @throws OpenAPIException
     * 
     * @return array
     */
    private function scopes(array $item)
    {
        if (isset($item['scopes']) && !is_array($item['scopes']))
            throw new OpenAPIException("scopes must be array type");

        return $item['scopes'] ?? [];
    }

    /**
     * @return static
     */
    protected function generatePath()
    {
        $actionClassMethodInstance = $this->getActionClassMethodInstance($this->getActionClassInstance());

        $docBlock = $actionClassMethodInstance ? ($actionClassMethodInstance->getDocComment() ?: '') : '';

        $this->addActionParameters($docBlock)->setDocsPaths($docBlock);

        if ($this->hasSecurityDefinitions) {
            $this->addActionScopes();
        }

        return $this;
    }

    /**
     * @param string $contentType
     * 
     * @return static
     */
    public function setContentType(string $contentType = "application/json")
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
     * @param array $body
     * 
     * @return array
     */
    protected function requestBodyBuilder(array $body)
    {
        [
            'body'          => $content,
            'parameters'    => $parameters
        ] = $body;

        $requestBody = compact('content');

        return compact('requestBody', 'parameters');
    }

    /**
     * @param array $response
     * 
     * @return array
     */
    protected function responseBodyBuilder(array $body)
    {
        return collect($body)->mapWithKeys(
            fn ($content) => $this->responseBodyBuilderCallback($content)
        )->toArray();
    }

    /**
     * @param array $content
     * 
     * @return array
     */
    private function responseBodyBuilderCallback(array $content)
    {
        ['code' => $code, 'description' => $description] = $content;

        $body = [];

        if (isset($content["body"]) && !empty($content["body"])) {
            // $items = collect($content["body"])->transform(
            //     fn ($item) => $this->itemsHandle($item)
            // )->toArray();

            $body = $this->setContentType()->contentHandle($content["body"]);
        }

        $code = [
            $code => $body + compact('description')
        ];

        return $code;
    }

    /**
     * @param array $item
     * 
     * @return array
     */
    private function itemsHandle(array $item)
    {
        ['type' => $type] = $item;

        if ($type === 'array') {
            $items = [
                'type' => 'object',
                'properties' => $item['body']
            ];

            return compact('type', 'items');
        }

        return $item;
    }

    /**
     * @param array $items
     * 
     * @return array
     */
    private function contentHandle(array $items)
    {
        $content = [
            $this->getContentType() => [
                'schema' => [
                    'type' => 'object',
                    'properties' => $items
                ]
            ]
        ];

        return compact('content');
    }

    /**
     * @param string $docBlock
     * 
     * @return static
     */
    protected function setDocsPaths(string $docBlock)
    {
        [
            'responseBody'  => $responseBody,
            'requestBody'   => $requestBodys,
            'exceptRoute'   => $exceptRoute,
        ] = $data = $this->parseActionMethodDocBlock($docBlock);

        if ($exceptRoute) {
            unset($this->docs['paths'][$this->route->uri()][$this->getMethod()]);

            return $this;
        }

        $data = $this->dataBuilder($requestBodys, $responseBody, $data);

        if (empty($parameters) || empty($requestBody['content']))
            $this->docs['paths'][$this->route->uri()][$this->getMethod()] = array_replace_recursive($this->docs['paths'][$this->route->uri()][$this->getMethod()], $data);
        else
            $this->docs['paths'][$this->route->uri()][$this->getMethod()] = $data;

        return $this;
    }

    /**
     * @param array $requestBodys
     * @param array $responseBody
     * @param array $data
     * 
     * @return array
     */
    private function dataBuilder(array $requestBodys, array $responseBody, array $data)
    {
        $responses = $this->responseBodyBuilder($responseBody);

        [
            'parameters'    => $parameters,
            'requestBody'   => $requestBody
        ] = $this->requestBodyBuilder($requestBodys);

        $data = array_replace_recursive(
            Arr::except(
                $data,
                [
                    'exceptRoute', 'requestBodys', 'responseBody'
                ]
            ),
            compact('responses')
        );

        !empty($parameters) && $data += compact('parameters');

        !empty($requestBody['content']) && $data += compact('requestBody');

        $data = array_filter($data, function ($item) {
            return $item !== null;
        });

        return $data;
    }

    /**
     * @param string $docBlock
     * 
     * @return static
     */
    protected function addActionParameters(string $docBlock)
    {
        if ($rules = $this->getFormRules()) {
            [
                'rulesInstance' => $rulesInstance,
                'rules' => $rules
            ] = $rules;
        }

        $parameters = (new Parameters\PathParameterGenerator($this->route->originalUri(), $this->parsePathsActionDocBlock($docBlock)))->getParameters();

        if (!empty($rules)) {
            $this->rules = $rules;

            $parameterGenerator = $this->getParameterGenerator($rules, $rulesInstance);

            $parameters = array_merge_recursive($parameters, $parameterGenerator->getParameters());
        }

        $this->docs['paths'][$this->route->uri()][$this->getMethod()] = [];

        if (!empty($parameters)) {
            $this->docs['paths'][$this->route->uri()][$this->getMethod()] = $parameters;
        }

        return $this;
    }

    /**
     * @return static
     */
    protected function addActionScopes()
    {
        foreach ($this->route->middleware() as $middleware) {
            if ($this->isPassportScopeMiddleware($middleware)) {
                $this->docs['paths'][$this->route->uri()][$this->getMethod()]['security'] = [
                    // FIXME: key oauth2 need fix me
                    'oauth2' => $middleware->parameters(),
                ];
            }
        }

        return $this;
    }

    /**
     * @return array
     */
    protected function getFormRules(): array
    {
        $actionClassMethodInstance = $this->getActionClassMethodInstance($this->getActionClassInstance());

        if (!$actionClassMethodInstance) {
            return [];
        }

        $parameters = $actionClassMethodInstance->getParameters();

        foreach ($parameters as $parameter) {
            // fix issues https://github.com/mtrajano/laravel-swagger/issues/60
            $class = $parameter->getType() && !$parameter->getType()->isBuiltin()
                ? new \ReflectionClass($parameter->getType()->getName())
                : null;
            // fix https://github.com/mtrajano/laravel-swagger/issues/60 bug
            if ($class && $class->isSubclassOf(FormRequest::class)) {
                return [
                    'rulesInstance' => $class->getMethod('rules'),
                    'rules' => $class->newInstance()->rules() ?: [],
                ];
            }
        }

        return [];
    }

    /**
     * @param array $rules
     * @param ReflectionMethod $rulesInstance
     * 
     * @return Parameters\QueryParameterGenerator|Parameters\RequestBodyGenerator
     */
    protected function getParameterGenerator(array $rules, ReflectionMethod $rulesInstance = null)
    {
        $docBlock = $rulesInstance ? ($rulesInstance->getDocComment() ?: '') : '';

        $docFields = $this->parseFieldsActionDocBlock($docBlock);

        switch ($this->getMethod()) {
            case 'post':
            case 'put':
            case 'patch':
                return new Parameters\RequestBodyGenerator($rules, $docFields);
            default:
                return new Parameters\QueryParameterGenerator($rules, $docFields);
                break;
        }
    }

    /**
     * @param string $parsedComment
     * 
     * @return array
     */
    private function parsePathsActionDocBlock(string $parsedComment)
    {
        if (!$parsedComment) return [];

        $docBlock = $this->docParser->create($parsedComment);

        $paths = $this->paths($docBlock);

        return $paths;
    }

    /**
     * @param DocBlock $docBlock
     * @param array $fields
     * 
     * @return array
     */
    protected function paths(DocBlock $docBlock, array $paths = [])
    {
        $pathsDoc = $this->getDocsTagsByName($docBlock, 'path');

        !empty($pathsDoc) && $paths = collect($pathsDoc)->map(fn ($path) => $this->docsBody($path))->pop();

        return $paths;
    }

    /**
     * @param string $parsedComment
     * 
     * @return array
     */
    private function parseFieldsActionDocBlock(string $parsedComment)
    {
        if (!$parsedComment) return [];

        $docBlock = $this->docParser->create($parsedComment);

        $fields = $this->fields($docBlock);

        return $fields;
    }

    /**
     * @param DocBlock $docBlock
     * @param array $fields
     * 
     * @return array
     */
    protected function fields(DocBlock $docBlock, array $fields = [])
    {
        $fieldsDoc = $this->getDocsTagsByName($docBlock, 'fields');

        !empty($fieldsDoc) && $fields = collect($fieldsDoc)->map(fn ($field) => $this->docsBody($field))->pop();

        return $fields;
    }

    /**
     * @return ReflectionClass|null
     */
    private function getActionClassInstance(): ?ReflectionClass
    {
        [$class] = Str::parseCallback($this->route->getActionName());

        if (!$class) {
            return null;
        }

        return new ReflectionClass($class);
    }

    /**
     * @param ReflectionClass $actionInstance
     * 
     * @return ReflectionMethod
     */
    private function getActionClassMethodInstance(ReflectionClass $actionInstance): ?ReflectionMethod
    {
        [$class, $method] = Str::parseCallback($this->route->getActionName());

        if (!$class || !$method || ($class && !method_exists($class, $method))) {
            return null;
        }

        return $actionInstance->getMethod($method);
    }

    /**
     * @param string $docBlock
     * 
     * @return array
     */
    private function parseActionMethodDocBlock(string $docBlock)
    {
        if (!$docBlock || !$this->config['parseDocBlock']) {
            return $this->parseActionDocDefaultReturn();
        }

        try {
            $parsedComment = $this->docParser->create($docBlock);

            $responseBody = $this->responseBody($parsedComment);

            [
                'summary'       => $summary,
                'description'   => $description,
                'body'          => $body,
                'parameters'    => $parameters,
            ] = $this->requestBody($parsedComment);

            $requestBody = compact('body', 'parameters') ?: [];

            $deprecated = $parsedComment->hasTag('deprecated');

            $exceptRoute = $parsedComment->hasTag('exceptRoute');

            $summary = $summary ?: $parsedComment->getSummary();

            $description = $description ?: (string) $parsedComment->getDescription();

            return array_replace_recursive(
                Arr::except(
                    $this->requestBody($parsedComment),
                    [
                        'body', 'parameters'
                    ]
                ),
                compact('summary', 'description', 'deprecated', 'responseBody', 'requestBody', 'exceptRoute')
            );
        } catch (JsonFormatException $e) {
            throw $e;
        } catch (OpenAPIException $e) {
            throw $e;
        } catch (\Exception $e) {
            $code = 500;

            return $this->parseActionDocDefaultReturn(compact('code'));
        }
    }

    /**
     * @param DocBlock $docBlock
     * @param string $tagName
     * 
     * @return \phpDocumentor\Reflection\DocBlock\Tag[]
     */
    private function getDocsTagsByName(DocBlock $docBlock, string $tagName)
    {
        return $docBlock->getTagsByName($tagName) ?:
            $docBlock->getTagsByName(ucfirst($tagName));
    }

    /**
     * @param DocBlock $docBlock
     * @param array $requestBdoy
     * 
     * @return array
     */
    protected function requestBody(DocBlock $docBlock, array $requestBody = [])
    {
        $requestDoc = $this->getDocsTagsByName($docBlock, 'request');

        !empty($requestDoc) && $requestBody = collect($requestDoc)->map(fn ($request) => $this->requestDocBody($request))->pop();

        return $requestBody ?: $this->requestDefaultArray();
    }

    /**
     * @param Generic $docs
     * 
     * @return array
     */
    protected function requestDocBody(Generic $docs)
    {
        $data = $this->docsBody($docs);

        return array_replace_recursive($this->requestDefaultArray(), $data);
    }

    /**
     * @return array
     */
    protected function requestDefaultArray()
    {
        return [
            'summary'       => null,
            'description'   => null,
            'tags'          => null,
            'security'      => null,
            'body'          => null,
            'parameters'    => null,
        ];
    }

    /**
     * @param DocBlock $docBlock
     * @param array $responseBody
     * 
     * @return array
     */
    protected function responseBody(DocBlock $docBlock, array $responseBody = [])
    {
        $responseDoc = $this->getDocsTagsByName($docBlock, 'response');

        !empty($responseDoc) && $responseBody = collect($responseDoc)->map(fn ($response) => $this->responseDocBody($response))->toArray();

        return $responseBody;
    }

    /**
     * @param Generic $docs
     * 
     * @return array
     */
    private function responseDocBody(Generic $docs)
    {
        $data = array_replace_recursive(
            [
                'code'          => 200,
                'body'          => []
            ],
            $this->docsBody($docs)
        );

        $description = \Illuminate\Http\Response::$statusTexts[$data['code']];

        return $data + compact('description');
    }

    /**
     * @param Generic $response
     * 
     * @return array
     */
    private function docsBody(Generic $docs)
    {
        return $this->phpDoc($docs->getDescription()->getBodyTemplate());
    }

    /**
     * @param integer $code
     * @param boolean $isDeprecated
     * @param string $summary
     * @param string $description
     * @param array $body
     * 
     * @return array
     */
    private function parseActionDocDefaultReturn(array $options = [])
    {
        $default = [
            'code'          => 200,
            'deprecated'    => false,
            'summary'       => '',
            'description'   => '',
            'responseBody'  => [],
            'tags'          => null,
            'security'      => null,
            'requestBody'   => [
                'body' => [],
                'parameters' => []
            ],
            'exceptRoute'   => false,
        ];

        $options = array_replace_recursive($default, $options);

        $responseBody = [[
            "code"          => $options['code'],
            "body"          => $options['responseBody'],
            "description"   => \Illuminate\Http\Response::$statusTexts[$options['code']]
        ]];

        return $options + compact('responseBody');
    }

    /**
     * @param string $docs
     * 
     * @throws OpenAPIException
     * 
     * @return array
     */
    private function phpDoc(string $docParser)
    {
        $jsonString = Str::of($docParser)
            ->replace('(', '')
            ->replace(')', '');

        $data = json_decode($jsonString, true);

        ['code' => $code, 'message' => $message] = json_error_check();

        if ($code) {
            throw new JsonFormatException(json_encode([
                'ErrorMessage' => $message,
                'JsonString' => $this->errorJsonString($jsonString->__toString())
            ]), 403);
        }

        return $data;
    }

    private function errorJsonString($jsonString)
    {
        $jsonString = trim(preg_replace('/\s+/', ' ', $jsonString));

        return stripslashes($jsonString);
    }

    /**
     * @return boolean
     */
    private function isFilteredRoute()
    {
        return !preg_match('/^' . preg_quote($this->getRouteFilter(), '/') . '/', $this->route->uri());
    }

    /**
     * @return boolean
     */
    private function isExceptRoute()
    {
        return preg_match('/^' . preg_quote($this->getExceptRoute(), '/') . '/', $this->route->uri());
    }

    /**
     * @param string $path
     * 
     * @return string
     */
    private function getEndpoint(string $path)
    {
        return rtrim($this->config['baseic']['servers'][0]['url'], '/') . $path;
    }

    /**
     * @return array
     */
    private function generateOauthScopes()
    {
        if (!class_exists(\Laravel\Passport\Passport::class)) {
            return [];
        }

        $scopes = \Laravel\Passport\Passport::scopes()->toArray();

        return array_combine(array_column($scopes, 'id'), array_column($scopes, 'description'));
    }

    /**
     * @throws OpenAPIException
     * 
     * @return static
     */
    private function validateAuthFlow(string $flow)
    {
        if (!in_array($flow, ['password', 'application', 'implicit', 'accessCode'])) {
            throw new OpenAPIException('Invalid OAuth flow passed');
        }

        return $this;
    }

    /**
     * @param DataObjects\Middleware $middleware
     * 
     * @return boolean
     */
    private function isPassportScopeMiddleware(DataObjects\Middleware $middleware)
    {
        $resolver = $this->getMiddlewareResolver($middleware->name());

        return $resolver === \Laravel\Passport\Http\Middleware\CheckScopes::class ||
            $resolver === \Laravel\Passport\Http\Middleware\CheckForAnyScope::class;
    }

    /**
     * @param string $middleware
     * 
     * @return array|null
     */
    private function getMiddlewareResolver(string $middleware)
    {
        $middlewareMap = app('router')->getMiddleware();

        return $middlewareMap[$middleware] ?? null;
    }

    /**
     * @param string $method
     * 
     * @return static
     */
    public function setMethod(string $method)
    {
        $this->method = $method;

        return $this;
    }

    /**
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }
}
