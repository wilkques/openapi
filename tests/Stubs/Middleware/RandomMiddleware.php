<?php

namespace Wilkques\OpenAPI\Tests\Stubs\Middleware;

class RandomMiddleware
{
    public function handle($request, $next)
    {
        return $next($request);
    }
}
