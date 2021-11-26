<?php

namespace App\Model;

use Doctrine\Common\Collections\ArrayCollection;

/**
 * @extends ArrayCollection<int, KeyValue>
 */
class KeyValueCollection extends ArrayCollection
{
    public function getByKey(string $key): ?KeyValue
    {
        $filteredCollection = $this->filter(function (KeyValue $element) use ($key) {
            return $key === $element->getKey();
        });

        $element = $filteredCollection->first();

        return $element instanceof KeyValue ? $element : null;
    }
}
