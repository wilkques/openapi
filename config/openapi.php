<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Basic Info
    |--------------------------------------------------------------------------
    |
    | The basic info for the application such as the title description,
    | description, version, etc...
    |
    */

    'baseic' => [

        'openapi' => '3.0.3',

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

    ],

    'storage' => storage_path('api-docs'),

    /*
    |--------------------------------------------------------------------------
    | namespace Controllers
    |--------------------------------------------------------------------------
    |
    | namespace in the following array will be generate openapi in the paths array
    |
    */

    'namespace' => [
        // 'App\Http\Controllers',
    ],


    /*
    |--------------------------------------------------------------------------
    | route except
    |--------------------------------------------------------------------------
    |
    | route in the following array will not generate openapi in the paths array
    |
    */

    'except' => [
        'routes' => [
            'uri'   => [
                // route uri
            ],
            'name'  => [
                // route name or as
            ]
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Ignore methods
    |--------------------------------------------------------------------------
    |
    | Methods in the following array will be ignored in the paths array
    |
    */

    'ignoredMethods' => [
        'head',
    ],

    /*
    |--------------------------------------------------------------------------
    | Parse summary and descriptions
    |--------------------------------------------------------------------------
    |
    | If true will parse the action method docBlock and make it's best guess
    | for what is the summary and description. Usually the first line will be
    | used as the route's summary and any paragraphs below (other than
    | annotations) will be used as the description. It will also parse any
    | appropriate annotations, such as @deprecated.
    |
    */

    'parseDocBlock' => true,

    /*
    |--------------------------------------------------------------------------
    | Security
    |--------------------------------------------------------------------------
    |
    | API security definitions. Will be generated into documentation file.
    |
    */

    'securityDefinitions' => [
        'securitySchemes' => [
            /*
            'apikey' => [ // Unique name of security
                'type' => 'apiKey', // The type of the security scheme. Valid values are "basic", "apiKey" or "oauth2".
                'description' => 'A short description for security scheme',
                'name' => 'api_key', // The name of the header or query parameter to be used.
                'in' => 'header', // The location of the API key. Valid values are "query" or "header".
            ],

            'oauth2' => [ // Unique name of security
                'type' => 'oauth2', // The type of the security scheme. Valid values are "basic", "apiKey" or "oauth2".
                'description' => 'A short description for oauth2 security scheme.',
                'flow' => 'implicit', // The flow used by the OAuth2 security scheme. Valid values are "implicit", "password", "application" or "accessCode".
                'authorizationUrl' => 'http://example.com/auth', // The authorization URL to be used for (implicit/accessCode)
                //'tokenUrl' => 'http://example.com/auth' // The authorization URL to be used for (password/application/accessCode)
                'scopes' => [
                    'user-read',
                    'user-write'
                ]
            ],
            // Open API 3.0 support
            'passport' => [ // Unique name of security
                'type' => 'oauth2', // The type of the security scheme. Valid values are "basic", "apiKey" or "oauth2".
                'description' => 'Laravel passport oauth2 security.',
                'in' => 'header',
                'scheme' => 'https',
                'flows' => [
                    "password" => [
                        "authorizationUrl" => config('app.url') . '/oauth/authorize',
                        "tokenUrl" => config('app.url') . '/oauth/token',
                        "refreshUrl" => config('app.url') . '/token/refresh',
                        "scopes" => []
                    ],
                ],
            ],
            */
        ],
        'security' => [
            [
                /*
                'oauth2' => [
                    'read',
                    'write'
                ],

                'passport' => []
                */
            ],
        ],
    ]
];
