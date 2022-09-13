<?php

declare(strict_types=1);

namespace App\Model;

use App\Model\InstanceSorter\InstanceCreatedDateSorter;
use App\Model\InstanceSorter\InstanceSorterInterface;

/**
 * @implements \IteratorAggregate<int, Instance>
 */
class InstanceCollection implements \JsonSerializable, \Countable, \IteratorAggregate
{
    /**
     * @param array<int, Instance> $instances
     */
    public function __construct(
        private readonly array $instances = [],
    ) {
    }

    public function findByIP(string $ip): ?Instance
    {
        foreach ($this as $instance) {
            if ($instance->hasIp($ip)) {
                return $instance;
            }
        }

        return null;
    }

    public function getNewest(): ?Instance
    {
        $sortedCollection = $this->sort(new InstanceCreatedDateSorter());
        $first = $sortedCollection->instances[0] ?? null;

        return $first instanceof Instance ? $first : null;
    }

    public function sort(InstanceSorterInterface $sorter): InstanceCollection
    {
        $instances = $this->instances;

        usort($instances, function (Instance $a, Instance $b) use ($sorter): int {
            return $sorter->sort($a, $b);
        });

        return new InstanceCollection($instances);
    }

    /**
     * @return array<mixed>
     */
    public function jsonSerialize(): array
    {
        $data = [];
        foreach ($this as $instance) {
            $data[] = $instance->jsonSerialize();
        }

        return $data;
    }

    /**
     * @return \Traversable<int, Instance>
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->instances);
    }

    public function count(): int
    {
        return count($this->instances);
    }
}
