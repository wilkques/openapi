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

    /**
     * test json controller
     * 
     * @Path({
     *      "id": {
     *          "description": "id",
     *          "example": "123456789"
     *      }
     * })
     * @Request({
     *      "summary": "test get /api/test index",
     *      "description": "Test route descriptioncd",
     *      "tags": ["User"],
     *      "security": [{"bearerAuth": []}]
     * })
     * @Response({
     *     "code": 302
     * })
     * @Response({
     *     "code": 400
     * })
     * @Response({
     *     "code": 500
     * })
     * @Response({
     *      "code": 200,
     *      "body": {
     *          "data": {
     *              "type": "array",
     *              "items": {
     *                  "type": "object",
     *                  "properties": {
     *                      "id": {
     *                          "type": "integer",
     *                          "description": "file id",
     *                          "example": 1
     *                      },
     *                      "name": {
     *                          "type": "string",
     *                          "description": "file name",
     *                          "example": "name"
     *                      }
     *                  }
     *              }
     *          }
     *      }
     * })
     */
    public function json()
    {
        return response([]);
    }

    /**
     * test yaml controller
     * 
     * @Path(
     *      id:
     *          description: "id"
     *          example: 123456789
     * 
     * )
     * @Request(
     *      summary: "test get /api/test index"
     *      description: "Test route descriptioncd"
     *      tags:
     *      - "User"
     *      security: 
     *      - bearerAuth: []
     * )
     * @Response(
     *     code: 302
     * )
     * @Response(
     *     code: 400
     * )
     * @Response(
     *     code: 500
     * )
     * @Response(
     *      code: 200
     *      body:
     *          data:
     *              type: "array"
     *              items:
     *                  type: "object"
     *                  properties:
     *                      id:
     *                          type: "integer"
     *                          description: "file id"
     *                          example: 1
     *                      name: 
     *                          type: "string"
     *                          description: "file name"
     *                          example: "name"
     * )
     */
    public function yaml()
    {
        return response([]);
    }
}
