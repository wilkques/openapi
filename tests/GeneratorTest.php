<?php

namespace Wilkques\OpenAPI\Tests;

use Wilkques\OpenAPI\Generator;

class GeneratorTest extends TestCase
{
    /** @var Generator */
    protected $generator;

    protected $endpoints = [
        '/users',
        '/users/{id}',
        '/users/details',
        '/users/ping',
        '/api',
        '/api/store',
        '/oauth/authorize',
        '/oauth/token',
        '/oauth/tokens',
        '/oauth/tokens/{token_id}',
        '/oauth/token/refresh',
        '/oauth/clients',
        '/oauth/clients/{client_id}',
        '/oauth/scopes',
        '/oauth/personal-access-tokens',
        '/oauth/personal-access-tokens/{token_id}',
        '/api/doc/json',
        '/api/doc/yaml',
    ];

    public function setUp(): void
    {
        parent::setUp();
    }

    public function testRequiredBaseInfo()
    {
        $docs = $this->getDocsWithNewConfig();

        $this->assertArrayHasKey('openapi', $docs);
        $this->assertArrayHasKey('info', $docs);
        $this->assertArrayHasKey('title', $docs['info']);
        $this->assertArrayHasKey('description', $docs['info']);
        $this->assertArrayHasKey('version', $docs['info']);
        $this->assertArrayHasKey('servers', $docs);
        $this->assertArrayHasKey('paths', $docs);

        return $docs;
    }

    public function testRequiredBaseInfoData()
    {
        $docs = $this->getDocsWithNewConfig([
            'baseic' => [
                'openapi' => '3.0.0',
                'info' => [
                    'title' => config('app.name'),
                    'description' => 'This is my awesome site, please enjoy it',
                    'version' => '1.0.0',
                ],
                "servers" => [
                    [
                        "url" => env('APP_DOMAIN', '') ? "{schema}://" . env('APP_DOMAIN') : '/',
                        "description" => "server",
                        "variables" => [
                            "schema" => [
                                "enum" => [
                                    "http",
                                    "https",
                                ],
                                "default" => "http"
                            ],
                            "port" => [
                                "enum" => [
                                    "8443",
                                    "443"
                                ],
                                "default" => "443"
                            ],
                            "basePath" => [
                                "default" => "v1"
                            ]
                        ]
                    ],
                ],
            ]
        ]);

        $this->assertEquals('3.0.0', $docs['openapi']);
        $this->assertEquals(config('app.name'), $docs['info']['title']);
        $this->assertEquals('This is my awesome site, please enjoy it', $docs['info']['description']);
        $this->assertEquals('1.0.0', $docs['info']['version']);
        $this->assertEquals(['http', 'https'], $docs['servers'][0]['variables']['schema']['enum']);
        $this->assertEquals(['8443', '443'], $docs['servers'][0]['variables']['port']['enum']);
        $this->assertArrayHasKey('default', $docs['servers'][0]['variables']['basePath']);
    }

    public function testSecurityDefinitionsAccessCodeFlow()
    {
        $docs = $this->getDocsWithNewConfig([
            'components' => [
                'securitySchemes' => [
                    'oauth2' => [ // Unique name of security
                        'type' => 'oauth2', // The type of the security scheme. Valid values are "basic", "apiKey" or "oauth2".
                        'description' => 'A short description for oauth2 security scheme.',
                        'flows' => [
                            // The flow used by the OAuth2 security scheme. Valid values are "implicit", "password", "application" or "accessCode".
                            'accessCode' => [
                                'authorizationUrl' => 'http://example.com/auth', // The authorization URL to be used for (implicit/accessCode)
                                'tokenUrl' => 'http://example.com/auth', // The authorization URL to be used for (password/application/accessCode)
                                'scopes' => [
                                    'user-read' => '',
                                    'user-write' => ''
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertArrayHasKey('components', $docs);

        $securityDefinition = $docs['components']['securitySchemes']['oauth2'];

        $this->assertEquals('oauth2', $securityDefinition['type']);
        $this->assertArrayHasKey('accessCode', $securityDefinition['flows']);
        $this->assertArrayHasKey('scopes', $securityDefinition['flows']['accessCode']);
        $this->assertArrayHasKey('authorizationUrl', $securityDefinition['flows']['accessCode']);
        $this->assertArrayHasKey('tokenUrl', $securityDefinition['flows']['accessCode']);
        $this->assertArrayHasKey('user-read', $securityDefinition['flows']['accessCode']['scopes']);
        $this->assertArrayHasKey('user-read', $securityDefinition['flows']['accessCode']['scopes']);
    }

    public function testSecurityDefinitionsImplicitFlow()
    {
        $docs = $this->getDocsWithNewConfig([
            'components' => [
                'securitySchemes' => [
                    'oauth2' => [ // Unique name of security
                        'type' => 'oauth2', // The type of the security scheme. Valid values are "basic", "apiKey" or "oauth2".
                        'description' => 'A short description for oauth2 security scheme.',
                        'flows' => [
                            // The flow used by the OAuth2 security scheme. Valid values are "implicit", "password", "application" or "accessCode".
                            'implicit' => [
                                'authorizationUrl' => 'http://example.com/auth', // The authorization URL to be used for (implicit/accessCode)
                                // 'tokenUrl' => 'http://example.com/auth', // The authorization URL to be used for (password/application/accessCode)
                                'scopes' => [
                                    'user-read' => '',
                                    'user-write' => ''
                                ]
                            ]
                        ],

                    ],
                ]
            ]
        ]);

        $this->assertArrayHasKey('components', $docs);

        $securityDefinition = $docs['components']['securitySchemes']['oauth2'];

        $this->assertEquals('oauth2', $securityDefinition['type']);
        $this->assertArrayHasKey('implicit', $securityDefinition['flows']);
        $this->assertArrayHasKey('authorizationUrl', $securityDefinition['flows']['implicit']);
        $this->assertArrayNotHasKey('tokenUrl', $securityDefinition['flows']['implicit']);
    }

    public function testSecurityDefinitionsPasswordFlow()
    {
        $docs = $this->getDocsWithNewConfig([
            'components' => [
                'securitySchemes' => [
                    'oauth2' => [ // Unique name of security
                        'type' => 'oauth2', // The type of the security scheme. Valid values are "basic", "apiKey" or "oauth2".
                        'description' => 'A short description for oauth2 security scheme.',
                        'flows' => [
                            // The flow used by the OAuth2 security scheme. Valid values are "implicit", "password", "application" or "accessCode".
                            'password' => [
                                // 'authorizationUrl' => 'http://example.com/auth', // The authorization URL to be used for (implicit/accessCode)
                                'tokenUrl' => 'http://example.com/auth', // The authorization URL to be used for (password/application/accessCode)
                                'scopes' => [
                                    'user-read' => '',
                                    'user-write' => ''
                                ]
                            ]
                        ],
                    ],
                ]
            ]
        ]);

        $this->assertArrayHasKey('components', $docs);

        $securityDefinition = $docs['components']['securitySchemes']['oauth2'];

        $this->assertEquals('oauth2', $securityDefinition['type']);
        $this->assertArrayHasKey('password', $securityDefinition['flows']);
        $this->assertArrayNotHasKey('authorizationUrl', $securityDefinition['flows']['password']);
        $this->assertArrayHasKey('tokenUrl', $securityDefinition['flows']['password']);
    }

    public function testSecurityDefinitionsApplicationFlow()
    {
        $docs = $this->getDocsWithNewConfig([
            'components' => [
                'securitySchemes' => [
                    'oauth2' => [ // Unique name of security
                        'type' => 'oauth2', // The type of the security scheme. Valid values are "basic", "apiKey" or "oauth2".
                        'description' => 'A short description for oauth2 security scheme.',
                        'flows' => [
                            // The flow used by the OAuth2 security scheme. Valid values are "implicit", "password", "application" or "accessCode".
                            'application' => [
                                // 'authorizationUrl' => 'http://example.com/auth', // The authorization URL to be used for (implicit/accessCode)
                                'tokenUrl' => 'http://example.com/auth', // The authorization URL to be used for (password/application/accessCode)
                                'scopes' => [
                                    'user-read' => '',
                                    'user-write' => ''
                                ]
                            ]
                        ],
                    ],
                ]
            ]
        ]);

        $this->assertArrayHasKey('components', $docs);

        $securityDefinition = $docs['components']['securitySchemes']['oauth2'];

        $this->assertEquals('oauth2', $securityDefinition['type']);
        $this->assertArrayHasKey('application', $securityDefinition['flows']);
        $this->assertArrayNotHasKey('authorizationUrl', $securityDefinition['flows']['application']);
        $this->assertArrayHasKey('tokenUrl', $securityDefinition['flows']['application']);
    }

    /**
     * @depends testRequiredBaseInfo
     */
    public function testHasPaths($docs)
    {
        dd(array_keys($docs['paths']));
        $this->assertEquals($this->endpoints, array_keys($docs['paths']));

        return $docs['paths'];
    }

    /**
     * @depends testHasPaths
     */
    public function testPathMethods($paths)
    {
        $this->assertArrayHasKey('get', $paths['/users']);
        $this->assertArrayNotHasKey('head', $paths['/users']);
        $this->assertArrayHasKey('post', $paths['/users']);

        $this->assertArrayHasKey('get', $paths['/users/{id}']);

        $this->assertArrayHasKey('get', $paths['/users/details']);
    }

    /**
     * @depends testHasPaths
     */
    public function testRouteData($paths)
    {
        $expectedPostDescription = <<<TEXT
    Data is validated [see description here](https://example.com) so no bad data can be passed.
    Please read the documentation for more information
    TEXT;

        $expectedPostDescription = str_replace(["\r\n", "\n"], "\n", $expectedPostDescription);

        $this->assertArrayHasKey('summary', $paths['/users']['get']);
        $this->assertArrayHasKey('description', $paths['/users']['get']);
        $this->assertArrayHasKey('responses', $paths['/users']['get']);
        $this->assertArrayHasKey('deprecated', $paths['/users']['get']);
        $this->assertArrayHasKey('parameters', $paths['/users']['get']);

        $this->assertEquals('Get a list of of users in the application', $paths['/users']['get']['summary']);
        $this->assertEquals(false, $paths['/users']['get']['deprecated']);
        $this->assertEquals('', $paths['/users']['get']['description']);

        $this->assertEquals('Store a new user in the application', $paths['/users']['post']['summary']);
        $this->assertEquals(true, $paths['/users']['post']['deprecated']);
        $this->assertEquals($expectedPostDescription, $paths['/users']['post']['description']);

        $this->assertEquals('', $paths['/users/{id}']['get']['summary']);
        $this->assertEquals(false, $paths['/users/{id}']['get']['deprecated']);
        $this->assertEquals('', $paths['/users/{id}']['get']['description']);

        $this->assertEquals('', $paths['/users/details']['get']['summary']);
        $this->assertEquals(true, $paths['/users/details']['get']['deprecated']);
        $this->assertEquals('', $paths['/users/details']['get']['description']);
    }

    /**
     * @depends testHasPaths
     */
    public function testRouteScopes()
    {
        $docs = $this->getDocsWithNewConfig([
            'components' => [
                'securitySchemes' => [
                    'oauth2' => [ // Unique name of security
                        'type' => 'oauth2', // The type of the security scheme. Valid values are "basic", "apiKey" or "oauth2".
                        'description' => 'A short description for oauth2 security scheme.',
                        'flows' => [
                            // The flow used by the OAuth2 security scheme. Valid values are "implicit", "password", "application" or "accessCode".
                            'implicit' => [
                                // 'authorizationUrl' => 'http://example.com/auth', // The authorization URL to be used for (implicit/accessCode)
                                //'tokenUrl' => 'http://example.com/auth' // The authorization URL to be used for (password/application/accessCode)
                                'scopes' => [
                                    'user-read' => '',
                                    'user-write' => ''
                                ]
                            ]
                        ],
                    ],
                ]
            ]
        ]);

        $this->assertEquals(['user-read'], $docs['paths']['/users']['get']['security']['oauth2']);
        $this->assertEquals(['user-write', 'user-read'], $docs['paths']['/users']['post']['security']['oauth2']);
    }

    public function testOverwriteIgnoreMethods()
    {
        $docs = $this->getDocsWithNewConfig([
            'ignoredMethods' => [],
            'only' => [
                'namespace' => [
                    // 'App\Http\Controllers',
                ],
            ]
        ]);

        $this->assertArrayHasKey('head', $docs['paths']['/users']);
    }

    public function testParseDocBlockFalse()
    {
        $docs = $this->getDocsWithNewConfig(['parseDocBlock' => false]);

        $this->assertEquals('', $docs['paths']['/users']['post']['summary']);
        $this->assertEquals(false, $docs['paths']['/users']['post']['deprecated']);
        $this->assertEquals('', $docs['paths']['/users']['post']['description']);
    }

    public function testOptionalData()
    {
        $docs = $this->getDocsWithNewConfig([
            'baseic' => [
                "servers" => [
                    [
                        "url" => env('APP_DOMAIN', '') ? "{schema}://" . env('APP_DOMAIN') : '/',
                        "description" => "server",
                        "variables" => [
                            "schema" => [
                                "enum" => [
                                    "http",
                                    "https",
                                ],
                                "default" => "http"
                            ],
                            "port" => [
                                "enum" => [
                                    "8443",
                                    "443"
                                ],
                                "default" => "443"
                            ],
                            "basePath" => [
                                "default" => "v1"
                            ]
                        ]
                    ],
                ],
            ]
        ]);

        $this->assertArrayHasKey('servers', $docs);
        $this->assertArrayHasKey('variables', $docs['servers'][0]);
        $this->assertArrayHasKey('schema', $docs['servers'][0]['variables']);
        $this->assertArrayHasKey('enum', $docs['servers'][0]['variables']['schema']);
        $this->assertArrayHasKey('default', $docs['servers'][0]['variables']['schema']);

        $this->assertContains('http', $docs['servers'][0]['variables']['schema']['enum']);
        $this->assertContains('https', $docs['servers'][0]['variables']['schema']['enum']);
        $this->assertEquals('http', $docs['servers'][0]['variables']['schema']['default']);

        $this->assertArrayHasKey('port', $docs['servers'][0]['variables']);
        $this->assertArrayHasKey('enum', $docs['servers'][0]['variables']['port']);
        $this->assertArrayHasKey('default', $docs['servers'][0]['variables']['port']);

        $this->assertContains('8443', $docs['servers'][0]['variables']['port']['enum']);
        $this->assertContains('443', $docs['servers'][0]['variables']['port']['enum']);
        $this->assertEquals('443', $docs['servers'][0]['variables']['port']['default']);

        $this->assertArrayHasKey('basePath', $docs['servers'][0]['variables']);
        $this->assertArrayHasKey('default', $docs['servers'][0]['variables']['basePath']);
        $this->assertEquals('v1', $docs['servers'][0]['variables']['basePath']['default']);
    }

    /**
     * @param string|null $routeFilter
     * @param array $expectedRoutes
     *
     * @dataProvider filtersRoutesProvider
     */
    public function testFiltersRoutes($routeFilter, $expectedRoutes)
    {
        $this->generator = new Generator(
            app(\Wilkques\OpenAPI\DataObjects\Routes::class),
            $this->config,
        );

        $docs = $this->generator->handle();

        sort($expectedRoutes);

        $intersect = array_values(array_intersect(array_keys($docs['paths']), $expectedRoutes));

        sort($intersect);

        $this->assertEquals($expectedRoutes, $intersect);
    }

    /**
     * @return array
     */
    public function filtersRoutesProvider()
    {
        return [
            'No Filter' => [null, $this->endpoints],
            '/api Filter' => ['/api', [
                '/api',
                '/api/store',
                '/api/doc/json',
                '/api/doc/yaml'
            ]],
            '/=nonexistant Filter' => ['/nonexistant', []],
        ];
    }

    /**
     * @param array $config
     * 
     * @return Generator
     */
    private function getDocsWithNewConfig(array $config = [])
    {
        if ($config) {
            $openapi = array_merge($this->config->get("openapi"), $config);

            $this->config->set("openapi", $openapi);

            $this->app->bind(\Illuminate\Config\Repository::class, fn () => $this->config);
        }

        return $this->app->make(Generator::class)->handle();
    }
}
