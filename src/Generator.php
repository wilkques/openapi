<?php

namespace Wilkques\OpenAPI;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
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
        $this->config = $config;
        $this->routeFilter = $routeFilter;
        $this->docParser = DocBlockFactory::createInstance();
        $this->hasSecurityDefinitions = false;
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

        !array_key_exists('paths', $this->docs) && $this->docs += ['paths' => []];

        isset($this->docs['servers']) && $this->docs['servers'] = array_unique($this->docs['servers'], SORT_REGULAR);

        return $this->docs;
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

        if (
            $this->routeOnlyNamespace($route->getActionNamespace()) ||
            $this->routeExcept($route) ||
            $this->routeFilter && $this->isFilteredRoute()
        ) return;

        !isset($this->docs['paths'][$this->route->uri()]) && $this->docs['paths'][$this->route->uri()] = [];

        $actionClassInstance = $this->getActionClassInstance();

        $docBlock = $actionClassInstance ? ($actionClassInstance->getDocComment() ?: '') : '';

        $this->parseActionDocBlock($docBlock);

        $closure($route->methods());

        return $route;
    }

    /**
     * @param string|null $namespace
     * 
     * @return boolean
     */
    private function routeOnlyNamespace(string $namespace = null)
    {
        return !empty($this->config['only']['namespace']) &&
            !Str::containsAll(
                Str::start($namespace, "\\"),
                array_map(
                    fn ($item) => Str::start($item, '\\'),
                    $this->config['only']['namespace']
                )
            );
    }

    /**
     * @param Route $route
     * 
     * @return boolean
     */
    private function routeExcept(Route $route)
    {
        return !empty($this->config['except']['routes']) &&
            $this->routeExceptUri($route->uri()) ||
            $this->routeExceptAsName($route->getAs());
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
        $securityDefinitions = $this->config['securityDefinitions']['securitySchemes'];

        return collect($securityDefinitions)->transform(function ($item, $key) {
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
        })->toArray();
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

        $flowData = [];

        if ($this->authorizationUrl($item) == '' && in_array($authFlow, ['implicit', 'accessCode'])) {
            $flowData['authorizationUrl'] = $this->getEndpoint(self::OAUTH_AUTHORIZE_PATH);
        }

        if ($this->tokenUrl($item) == '' && in_array($authFlow, ['password', 'application', 'accessCode'])) {
            $flowData['tokenUrl'] = $this->getEndpoint(self::OAUTH_TOKEN_PATH);
        }

        $flowData['scopes'] = empty($this->scopes($item)) ? $scopes : $item['scopes'];

        return [
            'type' => $item['type'],
            'description' => isset($item['description']) ? $item['description'] : '',
            'flows' => [
                $authFlow => $flowData
            ]
        ];
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

        if (isset($item['flows']['authorizationCode'])) {
            $data = $item['flows']['authorizationCode'];

            $this->authorizationUrl($data) == '' && $data['authorizationUrl'] = $authorizationUrl;

            $this->tokenUrl($data) == '' && $data['tokenUrl'] = $tokenUrl;

            $this->refreshUrl($data) == '' && $data['refreshUrl'] = $refreshUrl;

            empty($this->scopes($data)) && $data['scopes'] = $scopes;
        }

        if (!isset($item['flows']['authorizationCode'])) {
            $item['flows']['authorizationCode'] = compact('authorizationUrl', 'tokenUrl', 'refreshUrl', 'scopes');
        }

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

        $this->addActionParameters()->setDocsPaths($docBlock);

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
            $items = collect($content["body"])->transform(
                fn ($item) => $this->itemsHandle($item)
            )->toArray();

            $body = $this->setContentType()->contentHandle($items);
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
            'deprecated'    => $deprecated,
            'summary'       => $summary,
            'description'   => $description,
            'responseBody'  => $responseBody,
            'tags'          => $tags,
            'security'      => $security,
            'requestBody'   => $requestBodys,
        ] = $this->parseActionMethodDocBlock($docBlock);

        $responses = $this->responseBodyBuilder($responseBody);

        [
            'parameters'    => $parameters,
            'requestBody'   => $requestBody
        ] = $this->requestBodyBuilder($requestBodys);

        $data = compact('summary', 'description', 'deprecated', 'responses');

        !empty($parameters) && $data += compact('parameters');

        !empty($requestBody['content']) && $data += compact('requestBody');

        $tags && $data += compact('tags');

        $security && $data += compact('security');

        if (empty($parameters) || empty($requestBody['content']))
            $this->docs['paths'][$this->route->uri()][$this->getMethod()] += $data;
        else
            $this->docs['paths'][$this->route->uri()][$this->getMethod()] = $data;

        return $this;
    }

    /**
     * @return static
     */
    protected function addActionParameters()
    {
        if ($rules = $this->getFormRules()) {
            [
                'rulesInstance' => $rulesInstance,
                'rules' => $rules
            ] = $rules;
        }

        $parameters = (new Parameters\PathParameterGenerator($this->route->originalUri()))->getParameters();

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

        if (!$class || !$method) {
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
                'tags'          => $tags,
                'security'      => $security,
                'body'          => $body,
                'parameters'    => $parameters,
            ] = $this->requestBody($parsedComment);

            $requestBody = compact('body', 'parameters') ?: [];

            $deprecated = $parsedComment->hasTag('deprecated');

            $summary = $summary ?: $parsedComment->getSummary();

            $description = $description ?: (string) $parsedComment->getDescription();

            return compact('summary', 'description', 'tags', 'security', 'deprecated', 'responseBody', 'requestBody');
        } catch (JsonFormatException $e) {
            throw $e;
        } catch (OpenAPIException $e) {
            throw $e;
        } catch (\Exception $e) {
            return $this->parseActionDocDefaultReturn(500);
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
    private function parseActionDocDefaultReturn(
        int $code = 200,
        bool $deprecated = false,
        string $summary = '',
        string $description = '',
        array $responseBody = [],
        array $tags = null,
        array $security = null,
        array $requestBody = [
            'body' => [],
            'parameters' => []
        ]
    ) {
        $responseBody = [[
            "code"          => $code,
            "body"          => $responseBody,
            "description"   => \Illuminate\Http\Response::$statusTexts[$code]
        ]];

        return compact('deprecated', 'summary', 'description', 'responseBody', 'tags', 'security', 'requestBody');
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
        return !preg_match('/^' . preg_quote($this->routeFilter, '/') . '/', $this->route->uri());
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
