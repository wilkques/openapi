<?php

namespace Wilkques\OpenAPI\Tests\Stubs\Controllers;

use Illuminate\Routing\Controller;

class ApiController extends Controller
{
    public function index()
    {
        return response(['result' => 'success']);
    }

    public function store()
    {
        return response(['result' => 'success']);
    }
}
