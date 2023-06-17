<?php

use Illuminate\Support\Facades\Route;
use Wilkques\OpenAPI\GeneratorOpenAPIDoc;
use Wilkques\OpenAPI\Generator;

Route::get('api/doc/json', function () {
    return GeneratorOpenAPIDoc::format('json')->generator(new Generator(config('openapi')))->output();
});

Route::get('api/doc/yaml', function () {
    return GeneratorOpenAPIDoc::format('yaml')->generator(new Generator(config('openapi')))->output();
});