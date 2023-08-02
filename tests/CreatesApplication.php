<?php

namespace Wilkques\OpenAPI\Tests;

use Illuminate\Contracts\Console\Kernel;

trait CreatesApplication
{
    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        /*
        |--------------------------------------------------------------------------
        | Create The Application
        |--------------------------------------------------------------------------
        |
        | The first thing we will do is create a new Laravel application instance
        | which serves as the "glue" for all the components of Laravel, and is
        | the IoC container for the system binding all of the various parts.
        |
        */

        $app = new \Illuminate\Foundation\Application(
            $_ENV['APP_BASE_PATH'] ?? dirname(__DIR__)
        );

        return $app;
    }
}
