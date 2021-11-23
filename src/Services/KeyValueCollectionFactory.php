<?php

namespace App\Services;

use App\Model\KeyValue;
use App\Model\KeyValueCollection;

class KeyValueCollectionFactory
{
    public function createFromJsonForKeysMatchingPrefix(string $prefix, string $json): KeyValueCollection
    {
        return $this->doCreate($json, function (KeyValue $keyValue) use ($prefix): bool {
            return str_starts_with($keyValue->getKey(), $prefix);
        });
    }

    /**
     * @param null|callable(KeyValue): bool $validityChecker
     */
    private function doCreate(string $json, ?callable $validityChecker = null): KeyValueCollection
    {
        $keyValues = [];
        $itemsData = json_decode($json, true);

        if (is_array($itemsData)) {
            foreach ($itemsData as $key => $value) {
                if (is_string($key) && is_string($value)) {
                    $keyValue = new KeyValue($key, $value);

                    if ((is_callable($validityChecker) && $validityChecker($keyValue)) || null === $validityChecker) {
                        $keyValues[] = $keyValue;
                    }
                }
            }
        }

        return new KeyValueCollection($keyValues);
    }
}
