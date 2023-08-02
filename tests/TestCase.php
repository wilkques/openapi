<?php

namespace Wilkques\OpenAPI\Tests;

use Illuminate\Config\Repository as Config;
use Laravel\Passport\Passport;
use PHPUnit\Framework\TestCase as PHPunitTestCase;
use Wilkques\OpenAPI\Tests\Stubs\Middleware\RandomMiddleware;

class TestCase extends PHPunitTestCase
{
    use CreatesApplication;

    /** @var Config */
    protected $config;

    /** @var \Illuminate\Foundation\Application */
    protected $app;

    public function registerApp()
    {
        $app = $this->createApplication();

        $this->getPackageProviders($app);

        $this->config = $app->make('config');

        $app->scoped(\phpDocumentor\Reflection\DocBlockFactory::class, function () {
            return \phpDocumentor\Reflection\DocBlockFactory::createInstance();
        });

        $this->app = $app;

        $this->getEnvironmentSetUp($app);
    }

    protected function getPackageProviders()
    {
        return ['Wilkques\OpenAPI\OpenAPIServiceProvider'];
    }

    protected function getEnvironmentSetUp()
    {
        /** @var \Illuminate\Routing\Router */
        $router = $this->app->make('router');

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

        app()->scoped(\Illuminate\Routing\Router::class, fn() => $router);
    }
}
