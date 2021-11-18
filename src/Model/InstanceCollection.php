<?php

namespace App\Model;

use App\Model\InstanceSorter\InstanceCreatedDateSorter;
use App\Model\InstanceSorter\InstanceSorterInterface;

/**
 * @implements \IteratorAggregate<int|string, Instance>
 */
class InstanceCollection implements \IteratorAggregate, \Countable, \JsonSerializable
{
    /**
     * @var Instance[]
     */
    private array $instances;

    /**
     * @param Instance[] $instances
     */
    public function __construct(array $instances)
    {
        $this->instances = array_filter($instances, function ($item) {
            return $item instanceof Instance;
        });
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->instances);
    }

    public function count(): int
    {
        return count($this->instances);
    }

    public function getFirst(): ?Instance
    {
        return 0 === count($this)
            ? null
            : $this->instances[0];
    }

    public function getNewest(): ?Instance
    {
        $sortedCollection = $this->sortByCreatedDate();

        return $sortedCollection->getFirst();
    }

    public function sort(InstanceSorterInterface $sorter): self
    {
        $instances = $this->instances;

        usort($instances, function (Instance $a, Instance $b) use ($sorter): int {
            return $sorter->sort($a, $b);
        });

        return new InstanceCollection($instances);
    }

    public function filter(Filter $filter): self
    {
        $instances = [];

        foreach ($this as $instance) {
            if ($instance->isMatchedBy($filter)) {
                $instances[] = $instance;
            }
        }

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

    private function sortByCreatedDate(): self
    {
        return $this->sort(new InstanceCreatedDateSorter());
    }
}
