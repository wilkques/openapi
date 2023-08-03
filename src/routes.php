<?php

use Illuminate\Support\Facades\Route;
use Wilkques\OpenAPI\GeneratorOpenAPIDoc;
use Wilkques\OpenAPI\Generator;

app()->bind(\phpDocumentor\Reflection\DocBlockFactory::class, function () {
    return \phpDocumentor\Reflection\DocBlockFactory::createInstance();
});

Route::get('api/doc/json', function () {
    return GeneratorOpenAPIDoc::format('json')->generator(app()->make(Generator::class)->handle())->output();
});

Route::get('api/doc/yaml', function () {
    return GeneratorOpenAPIDoc::format('yaml')->generator(app()->make(Generator::class)->handle())->output();
});
