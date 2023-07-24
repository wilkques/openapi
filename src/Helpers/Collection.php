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
}