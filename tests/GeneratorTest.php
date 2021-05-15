<?php

namespace Wilkques\OpenAPI\Tests;

use Wilkques\OpenAPI\Generator;

class GeneratorTest extends TestCase
{
    protected $config;

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

        $this->generator = new Generator(
            $this->config = config('openapi')
        );
    }

    public function testRequiredBaseInfo()
    {
        $docs = $this->generator->generate();

        $this->assertArrayHasKey('openapi', $docs);
        $this->assertArrayHasKey('info', $docs);
        $this->assertArrayHasKey('title', $docs['info']);
        $this->assertArrayHasKey('description', $docs['info']);
        $this->assertArrayHasKey('version', $docs['info']);
        $this->assertArrayHasKey('host', $docs);
        $this->assertArrayHasKey('basePath', $docs);
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
                'host' => config('app.url'),
                'basePath' => '/api',
                'schemes' => [
                    'http',
                    'https',
                ],
                'consumes' => [
                    'application/json',
                ],
                'produces' => [
                    'application/json',
                ],
            ]
        ]);

        $this->assertEquals('3.0.0', $docs['openapi']);
        $this->assertEquals(config('app.name'), $docs['info']['title']);
        $this->assertEquals('This is my awesome site, please enjoy it', $docs['info']['description']);
        $this->assertEquals('1.0.0', $docs['info']['version']);
        $this->assertEquals(config('app.url'), $docs['host']);
        $this->assertEquals('/api', $docs['basePath']);
        $this->assertEquals(['http', 'https'], $docs['schemes']);
        $this->assertEquals(['application/json'], $docs['consumes']);
        $this->assertEquals(['application/json'], $docs['produces']);
    }

    public function testSecurityDefinitionsAccessCodeFlow()
    {
        $docs = $this->getDocsWithNewConfig([
            'securityDefinitions' => [
                'securitySchemes' => [
                    'oauth2' => [ // Unique name of security
                        'type' => 'oauth2', // The type of the security scheme. Valid values are "basic", "apiKey" or "oauth2".
                        'description' => 'A short description for oauth2 security scheme.',
                        'flow' => 'accessCode', // The flow used by the OAuth2 security scheme. Valid values are "implicit", "password", "application" or "accessCode".
                        // 'authorizationUrl' => 'http://example.com/auth', // The authorization URL to be used for (implicit/accessCode)
                        //'tokenUrl' => 'http://example.com/auth' // The authorization URL to be used for (password/application/accessCode)
                        'scopes' => [
                            'user-read' => '',
                            'user-write' => ''
                        ]
                    ],
                ]
            ]
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
            'securityDefinitions' => [
                'securitySchemes' => [
                    'oauth2' => [ // Unique name of security
                        'type' => 'oauth2', // The type of the security scheme. Valid values are "basic", "apiKey" or "oauth2".
                        'description' => 'A short description for oauth2 security scheme.',
                        'flow' => 'implicit', // The flow used by the OAuth2 security scheme. Valid values are "implicit", "password", "application" or "accessCode".
                        // 'authorizationUrl' => 'http://example.com/auth', // The authorization URL to be used for (implicit/accessCode)
                        //'tokenUrl' => 'http://example.com/auth' // The authorization URL to be used for (password/application/accessCode)
                        'scopes' => [
                            'user-read' => '',
                            'user-write' => ''
                        ]
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
            'securityDefinitions' => [
                'securitySchemes' => [
                    'oauth2' => [ // Unique name of security
                        'type' => 'oauth2', // The type of the security scheme. Valid values are "basic", "apiKey" or "oauth2".
                        'description' => 'A short description for oauth2 security scheme.',
                        'flow' => 'password', // The flow used by the OAuth2 security scheme. Valid values are "implicit", "password", "application" or "accessCode".
                        // 'authorizationUrl' => 'http://example.com/auth', // The authorization URL to be used for (implicit/accessCode)
                        //'tokenUrl' => 'http://example.com/auth' // The authorization URL to be used for (password/application/accessCode)
                        'scopes' => [
                            'user-read' => '',
                            'user-write' => ''
                        ]
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
            'securityDefinitions' => [
                'securitySchemes' => [
                    'oauth2' => [ // Unique name of security
                        'type' => 'oauth2', // The type of the security scheme. Valid values are "basic", "apiKey" or "oauth2".
                        'description' => 'A short description for oauth2 security scheme.',
                        'flow' => 'application', // The flow used by the OAuth2 security scheme. Valid values are "implicit", "password", "application" or "accessCode".
                        // 'authorizationUrl' => 'http://example.com/auth', // The authorization URL to be used for (implicit/accessCode)
                        //'tokenUrl' => 'http://example.com/auth' // The authorization URL to be used for (password/application/accessCode)
                        'scopes' => [
                            'user-read' => '',
                            'user-write' => ''
                        ]
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
        $expectedPostDescription = <<<'EOD'
Data is validated [see description here](https://example.com) so no bad data can be passed.
Please read the documentation for more information
EOD;

        $this->assertArrayHasKey('summary', $paths['/users']['get']);
        $this->assertArrayHasKey('description', $paths['/users']['get']);
        $this->assertArrayHasKey('responses', $paths['/users']['get']);
        $this->assertArrayHasKey('deprecated', $paths['/users']['get']);
        $this->assertArrayNotHasKey('parameters', $paths['/users']['get']);

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
            'securityDefinitions' => [
                'securitySchemes' => [
                    'oauth2' => [ // Unique name of security
                        'type' => 'oauth2', // The type of the security scheme. Valid values are "basic", "apiKey" or "oauth2".
                        'description' => 'A short description for oauth2 security scheme.',
                        'flow' => 'implicit', // The flow used by the OAuth2 security scheme. Valid values are "implicit", "password", "application" or "accessCode".
                        // 'authorizationUrl' => 'http://example.com/auth', // The authorization URL to be used for (implicit/accessCode)
                        //'tokenUrl' => 'http://example.com/auth' // The authorization URL to be used for (password/application/accessCode)
                        'scopes' => [
                            'user-read' => '',
                            'user-write' => ''
                        ]
                    ],
                ]
            ]
        ]);

        $this->assertEquals(['user-read'], $docs['paths']['/users']['get']['security']['oauth2']);
        $this->assertEquals(['user-write', 'user-read'], $docs['paths']['/users']['post']['security']['oauth2']);
    }

    public function testOverwriteIgnoreMethods()
    {
        $docs = $this->getDocsWithNewConfig(['ignoredMethods' => []]);

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
            'schemes' => [
                'http',
                'https',
            ],

            'consumes' => [
                'application/json',
            ],

            'produces' => [
                'application/json',
            ],
        ]);

        $this->assertArrayHasKey('schemes', $docs);
        $this->assertArrayHasKey('consumes', $docs);
        $this->assertArrayHasKey('produces', $docs);

        $this->assertContains('http', $docs['schemes']);
        $this->assertContains('https', $docs['schemes']);
        $this->assertContains('application/json', $docs['consumes']);
        $this->assertContains('application/json', $docs['produces']);
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
            $this->config,
            $routeFilter
        );

        $docs = $this->generator->generate();

        $this->assertEquals($expectedRoutes, array_keys($docs['paths']));
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

    private function getDocsWithNewConfig(array $config)
    {
        $config = array_merge($this->config, $config);

        return (new Generator($config))->generate();
    }
}
