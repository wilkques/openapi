<?php

namespace Wilkques\OpenAPI\Tests;

use Laravel\Passport\Passport;
use Wilkques\OpenAPI\Tests\Stubs\Middleware\RandomMiddleware;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

class TestCase extends OrchestraTestCase
{
    /** @var \Illuminate\Config\Repository */
    protected $config;

    /** @var \Illuminate\Foundation\Application */
    protected $app;

    protected function getPackageProviders($app)
    {
        return [\Wilkques\OpenAPI\OpenAPIServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app)
    {
        $this->artisan('vendor:publish', [
            '--provider' => \Wilkques\OpenAPI\OpenAPIServiceProvider::class
        ]);

        $this->config = $app->make('config');

        $app->bind(\phpDocumentor\Reflection\DocBlockFactory::class, function () {
            return \phpDocumentor\Reflection\DocBlockFactory::createInstance();
        });

        /** @var \Illuminate\Routing\Router */
        $router = $app->make('router');
        dd($router->getRoutes());
        $router->middleware(['some-middleware', 'scope:user-read'])->group(function () use ($router) {
            $router->get('/users', 'Wilkques\\OpenAPI\\Tests\\Stubs\\Controllers\\UserController@index');
            $router->get('/users/{id}', 'Wilkques\\OpenAPI\\Tests\\Stubs\\Controllers\\UserController@show');
            $router->post('/users', 'Wilkques\\OpenAPI\\Tests\\Stubs\\Controllers\\UserController@store')
                ->middleware('scopes:user-write,user-read');
            $router->get('/users/details', 'Wilkques\\OpenAPI\\Tests\\Stubs\\Controllers\\UserController@details');
            $router->get('/users/ping', function () {
                return 'pong';
            });
        });

        $router->get('/api', 'Wilkques\\OpenAPI\\Tests\\Stubs\\Controllers\\ApiController@index')
            ->middleware(RandomMiddleware::class);
        $router->put('/api/store', 'Wilkques\\OpenAPI\\Tests\\Stubs\\Controllers\\ApiController@store');

        Passport::routes();

        $router->aliasMiddleware('scopes', \Laravel\Passport\Http\Middleware\CheckScopes::class);
        $router->aliasMiddleware('scope', \Laravel\Passport\Http\Middleware\CheckForAnyScope::class);

        Passport::tokensCan([
            'user-read' => 'Read user information such as email, name and phone number',
            'user-write' => 'Update user information',
        ]);

        $app->bind(\Illuminate\Routing\Router::class, fn () => $router);

        $this->app = $app;
    }
}
