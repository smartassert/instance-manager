<?php

declare(strict_types=1);

namespace App\Services;

use App\Model\Secret;
use App\Model\SecretCollection;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class SecretFactory
{
    /**
     * @param string[] $prefixes
     */
    public function createFromJsonForKeysMatchingPrefix(array $prefixes, string $json): SecretCollection
    {
        $collection = $this->create($json);

        return $collection;

//        return $collection->filter(function (Secret $element) use ($prefixes): bool {
//            foreach ($prefixes as $prefix) {
//                if (str_starts_with($element->getKey(), $prefix)) {
//                    return true;
//                }
//            }
//
//            return false;
//        });
    }

    private function create(string $json): SecretCollection
    {
        $itemsData = json_decode($json, true);
        $collection = [];

        if (is_array($itemsData)) {
            foreach ($itemsData as $key => $value) {
                if (is_string($key) && is_string($value)) {
                    $collection[] = new Secret($key, $value);
                }
            }
        }

        return new SecretCollection($collection);
    }
}
