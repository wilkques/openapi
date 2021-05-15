<?php

namespace Wilkques\OpenAPI\Tests;

use Laravel\Passport\Passport;
use Wilkques\OpenAPI\Tests\Stubs\Middleware\RandomMiddleware;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app)
    {
        return ['Wilkques\OpenAPI\OpenAPIServiceProvider'];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['router']->middleware(['some-middleware', 'scope:user-read'])->group(function () use ($app) {
            $app['router']->get('/users', 'Wilkques\\OpenAPI\\Tests\\Stubs\\Controllers\\UserController@index');
            $app['router']->get('/users/{id}', 'Wilkques\\OpenAPI\\Tests\\Stubs\\Controllers\\UserController@show');
            $app['router']->post('/users', 'Wilkques\\OpenAPI\\Tests\\Stubs\\Controllers\\UserController@store')
                ->middleware('scopes:user-write,user-read');
            $app['router']->get('/users/details', 'Wilkques\\OpenAPI\\Tests\\Stubs\\Controllers\\UserController@details');
            $app['router']->get('/users/ping', function () {
                return 'pong';
            });
        });

        $app['router']->get('/api', 'Wilkques\\OpenAPI\\Tests\\Stubs\\Controllers\\ApiController@index')
            ->middleware(RandomMiddleware::class);
        $app['router']->put('/api/store', 'Wilkques\\OpenAPI\\Tests\\Stubs\\Controllers\\ApiController@store');

        Passport::routes();

        $app['router']->aliasMiddleware('scopes', \Laravel\Passport\Http\Middleware\CheckScopes::class);
        $app['router']->aliasMiddleware('scope', \Laravel\Passport\Http\Middleware\CheckForAnyScope::class);

        Passport::tokensCan([
            'user-read' => 'Read user information such as email, name and phone number',
            'user-write' => 'Update user information',
        ]);
    }
}
