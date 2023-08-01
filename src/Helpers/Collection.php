<?php

namespace Wilkques\OpenAPI\Helpers;

use Illuminate\Support\Collection as BaseCollection;

class Collection extends BaseCollection
{
    /**
     * Recursively merge the collection with the given items.
     *
     * @param  mixed  $items
     * @return static
     */
    public function mergeRecursiveDistinct($items)
    {
        return new static(array_merge_distinct_recursive($this->items, $this->getArrayableItems($items)));
    }
    
    /**
     * Recursively merge the collection with the given items.
     *
     * @param  string       $key
     * @param  mixed|null   $default
     * @return static
     */
    public function takeOffRecursive($key, $default = null)
    {
        return array_take_off_recursive($this->items, $key, $default);
    }
}