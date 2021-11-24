<?php

namespace App\Model;

class KeyValueCollection
{
    /**
     * @var KeyValue[]
     */
    private array $items;

    /**
     * @param array<mixed> $items
     */
    public function __construct(
        array $items
    ) {
        $this->items = array_filter($items, function (mixed $item) {
            return $item instanceof KeyValue;
        });
    }

    public function get(string $key): ?string
    {
        foreach ($this->items as $item) {
            if ($item->getKey() === $key) {
                return $item->getValue();
            }
        }

        return null;
    }
}
