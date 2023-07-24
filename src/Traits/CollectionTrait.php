<?php

namespace Wilkques\OpenAPI\Traits;

trait CollectionTrait
{
    /**
     * @param mixed $items
     * 
     * @return \Wilkques\OpenAPI\Helpers\Collection
     */
    protected function collection($items = [])
    {
        return new \Wilkques\OpenAPI\Helpers\Collection($items);
    }
}