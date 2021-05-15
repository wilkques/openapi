<?php

namespace Wilkques\OpenAPI\Tests\Stubs\Controllers;

use Illuminate\Routing\Controller;
use Wilkques\OpenAPI\Tests\Stubs\Requests\UserShowRequest;
use Wilkques\OpenAPI\Tests\Stubs\Requests\UserStoreRequest;

class UserController extends Controller
{
    /** Get a list of of users in the application */
    public function index()
    {
        return response([['first_name' => 'John'], ['first_name' => 'Jack']]);
    }

    public function show(UserShowRequest $request, $id)
    {
        return response(['first_name' => 'John']);
    }

    /**
     * Store a new user in the application
     *
     * Data is validated [see description here](https://example.com) so no bad data can be passed.
     * Please read the documentation for more information
     *
     * @param UserStoreRequest $request
     * @deprecated
     */
    public function store(UserStoreRequest $request)
    {
        return response($request->all());
    }

    /**
     * @deprecated
     */
    public function details()
    {
        return response([]);
    }
}
