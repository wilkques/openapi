<?php

if (!function_exists('strip_optional_char')) {
    /**
     * @param string $uri
     * 
     * @return string
     */
    function strip_optional_char($uri)
    {
        return str_replace('?', '', $uri);
    }
}

if (!function_exists('openapi_collect')) {
    /**
     * Create a collection from the given value.
     *
     * @param  mixed  $value
     * 
     * @return \Wilkques\OpenAPI\Helpers\Collection
     */
    function openapi_collect($value = [])
    {
        return new \Wilkques\OpenAPI\Helpers\Collection($value);
    }
}
