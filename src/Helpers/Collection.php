<?php

namespace Wilkques\OpenAPI\Helpers;

use Illuminate\Support\Collection as BaseCollection;
use Wilkques\Helpers\Arrays;

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

    /**
     * Get an item from the collection by key. If empty or null or false or 0, return default
     *
     * @param  mixed  $key
     * @param  mixed  $default
     * @return mixed|null
     */
    public function getToDefault($key, $default = null)
    {
        $values = $this->get($key, $default);

        if (!$values) {
            return $default;
        }

        return $values;
    }
}