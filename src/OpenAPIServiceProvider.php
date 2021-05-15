<?php

namespace Wilkques\OpenAPI;

use Illuminate\Support\ServiceProvider;

class OpenAPIServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateOpenAPIDocCommand::class,
            ]);
        }

        $source = __DIR__ . '/../config/openapi.php';

        $this->publishes([
            $source => config_path('openapi.php'),
        ]);

        $this->mergeConfigFrom(
            $source, 'openapi'
        );

        //Include routes
        $this->loadRoutesFrom(__DIR__.'/routes.php');
    }
}
