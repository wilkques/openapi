# Laravel OAS3

Laravel OAS3 scans your Laravel project's endpoints and auto generates a OAS3 documentation for you.

[![Build Status](https://travis-ci.com/wilkques/openapi.svg?branch=master)](https://travis-ci.org/wilkques/openapi)
[![Latest Stable Version](https://poser.pugx.org/wilkques/openapi/v/stable)](https://packagist.org/packages/wilkques/openapi)
[![License](https://poser.pugx.org/wilkques/openapi/license)](https://packagist.org/packages/wilkques/openapi)

## About

This package is heavily inspired by the [mtrajano/laravel-swagger](https://github.com/mtrajano/laravel-swagger)

Usage is pretty similar to the `mtrajano/laravel-swagger`

Laravel OAS3 works based on recommended practices by Laravel. It will parse your routes and generate a path object for each one. If you inject Form Request classes in your controller's actions as request validation, it will also generate the parameters for each request that has them. For the parameters, it will take into account wether the request is a GET/HEAD/DELETE or a POST/PUT/PATCH request and make its best guess as to the type of parameter object it should generate. It will also generate the path parameters if your route contains them. Finally, this package will also scan any documentation you have in your action methods and add it as summary and description to that path, along with any appropriate annotations such as @deprecated.

One thing to note is this library leans on being explicit. It will choose to include keys even if they have a default. For example it chooses to say a route has a deprecated value of false rather than leaving it out. I believe this makes reading the documentation easier by not leaving important information out. The file can be easily cleaned up afterwards if the user chooses to leave out the defaults.

## Installation

The package can easily be installed by running `composer require wilkques/openapi` in your project's root folder.

If you are running a version of Laravel < 5.5 also make sure you add `Wilkques\OpenAPI\OpenAPIServiceProvider::class` to the `providers` array in `config/app.php`.

This will register the artisan command that will be available to you.

You can also override the default config provided by the application by running `php artisan vendor:publish --provider "Wilkques\OpenAPI\OpenAPIServiceProvider"` in your projects root and change the configuration in the new `config/openapi.php` file created.

## Usage

Generating the OpenAPI documentation is easy, simply run `php artisan openapi:generate` in your project root. Keep in mind the command will simply print out the output in your console. If you want the docs saved in a file you can reroute the output like so: `php artisan openapi:generate > openapi.json`

If you wish to generate docs for a subset of your routes, you can pass a filter using `--filter`, for example: `php artisan openapi:generate --filter="/api"`

By default, laravel-openapi prints out the documentation in json format, if you want it in YAML format you can override the format using the `--format` flag. Make sure to have the yaml extension installed if you choose to do so.

Format options are:<br>
`json`<br>
`yaml`

## Example

Say you have a route `/api/users/{id}` that maps to `UserController@show`

Your sample controller might look like this:

@Response() and @Request() input json string
```php
/**
 * @server([
 *      {
 *          "url": "{schema}://package.co",
 *          "description": "local server",
 *          "variables": {
 *              "schema": {
 *                  "enum": ["https", "http"],
 *                  "default": "http"
 *              }
 *          }
 *      }
 * ])
 */
class TestController extends Controller
{
    /**
     * test controller
     * @Request({
     *      "summary": "test get /api/test index",
     *      "description": "Test route description",
     *      "tags": ["Test"],
     *      "security": [{"apikey": []}]
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
     *              "body": {
     *                  "id": {
     *                      "type": "integer",
     *                      "example": 1
     *                  },
     *                  "name": {
     *                      "type": "string",
     *                      "example": "name"
     *                  }
     *              }
     *          }
     *      }
     * })
     */
    public function index(IndexRequest $request)
```

And the FormRequest class might look like this:
```php
class UserShowRequest extends FormRequest
{
    public function rules()
    {
        return [
            'fields' => 'array'
            'show_relationships' => 'boolean|required'
        ];
    }
}

```

Running `artisan openapi:generate --output=storage/api-docs/api-docs.json` will generate the following file:
```json
{
    "openapi": "3.0.3",
    "info": {
        "title": "Laravel",
        "description": "Test",
        "version": "1.0.1"
    },
    "servers": [
        {
            "url": "\/",
            "description": "server",
            "variables": {
                "schema": {
                    "enum": [
                        "https",
                        "http"
                    ],
                    "default": "http"
                }
            }
        }
    ],
    "components": {
        "securitySchemes": {
            "bearerAuth": {
                "type": "http",
                "scheme": "bearer",
                "bearerFormat": "JWT"
            },
            "apikey": {
                "type": "apiKey",
                "description": "A short description for security scheme",
                "name": "api_key",
                "in": "header"
            }
        }
    },
    "paths": {
        "\/api\/user\/{id}": {
            "get": {
                "summary": "Return all the details of a user",
                "description": "Returns the user's first name, last name and address
 Please see the documentation [here](https://example.com/users) for more information",
                "deprecated": true,
                "parameters": [
                    {
                        "in": "path",
                        "name": "id",
                        "type": "integer",
                        "required": true,
                        "description": ""
                    },
                    {
                        "in": "query",
                        "name": "fields",
                        "type": "array",
                        "required": false,
                        "description": ""
                    },
                    {
                        "in": "query",
                        "name": "show_relationships",
                        "type": "boolean",
                        "required": true,
                        "description": ""
                    }
                ],
                "responses": {
                    "200": {
                        "description": "OK"
                    }
                }
            },
        }
    }
}
```

## Reference
- [OAS3](https://github.com/OAI/OpenAPI-Specification/blob/main/versions/3.0.3.md)
- [Laravel FormRequest](https://laravel.com/docs/8.x/validation#form-request-validation)