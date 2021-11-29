<?php

declare(strict_types=1);

namespace App\Model;

use App\Model\InstanceSorter\InstanceCreatedDateSorter;
use App\Model\InstanceSorter\InstanceSorterInterface;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * @extends ArrayCollection<int|string, Instance>
 */
class InstanceCollection extends ArrayCollection implements \JsonSerializable
{
    public function getNewest(): ?Instance
    {
        $sortedCollection = $this->sortByCreatedDate();
        $first = $sortedCollection->first();

        return $first instanceof Instance ? $first : null;
    }

    public function sort(InstanceSorterInterface $sorter): self
    {
        $instances = $this->toArray();

        usort($instances, function (Instance $a, Instance $b) use ($sorter): int {
            return $sorter->sort($a, $b);
        });

        return new InstanceCollection($instances);
    }

    public function filterByFilter(Filter $filter): self
    {
        $filteredCollection = $this->filter(function (Instance $instance) use ($filter) {
            return $instance->isMatchedBy($filter);
        });

        return new InstanceCollection(array_values($filteredCollection->toArray()));
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
