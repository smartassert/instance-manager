<?php

declare(strict_types=1);

namespace App\Services;

use App\Model\Secret;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class KeyValueCollectionFactory
{
    /**
     * @param string[] $prefixes
     *
     * @return Collection<int, Secret>
     */
    public function createFromJsonForKeysMatchingPrefix(array $prefixes, string $json): Collection
    {
        $collection = $this->create($json);

        return $collection->filter(function (Secret $element) use ($prefixes): bool {
            foreach ($prefixes as $prefix) {
                if (str_starts_with($element->getKey(), $prefix)) {
                    return true;
                }
            }

            return false;
        });
    }

    /**
     * @return Collection<int, Secret>
     */
    private function create(string $json): Collection
    {
        $itemsData = json_decode($json, true);
        $collection = new ArrayCollection();

        if (is_array($itemsData)) {
            foreach ($itemsData as $key => $value) {
                if (is_string($key) && is_string($value)) {
                    $collection->add(new Secret($key, $value));
                }
            }
        }

        return $collection;
    }
}
