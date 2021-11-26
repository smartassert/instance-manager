<?php

namespace App\Services;

use App\Model\KeyValue;
use App\Model\KeyValueCollection;

class KeyValueCollectionFactory
{
    public function createFromJsonForKeysMatchingPrefix(string $prefix, string $json): KeyValueCollection
    {
        $collection = $this->create($json);
        $filteredCollection = $collection->filter(function (KeyValue $element) use ($prefix) {
            return str_starts_with($element->getKey(), $prefix);
        });

        return new KeyValueCollection($filteredCollection->toArray());
    }

    private function create(string $json): KeyValueCollection
    {
        $itemsData = json_decode($json, true);

        $collection = new KeyValueCollection([]);

        if (is_array($itemsData)) {
            foreach ($itemsData as $key => $value) {
                if (is_string($key) && is_string($value)) {
                    $collection->add(new KeyValue($key, $value));
                }
            }
        }

        return $collection;
    }
}
