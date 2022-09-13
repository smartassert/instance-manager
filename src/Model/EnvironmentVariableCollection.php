<?php

declare(strict_types=1);

namespace App\Model;

/**
 * @implements \IteratorAggregate<int, EnvironmentVariable>
 */
class EnvironmentVariableCollection implements \IteratorAggregate
{
    /**
     * @param array<int, EnvironmentVariable> $environmentVariables
     */
    public function __construct(
        private readonly array $environmentVariables = [],
    ) {
    }

    /**
     * @return \Traversable<int, EnvironmentVariable>
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->environmentVariables);
    }

    public function add(EnvironmentVariable $environmentVariable): EnvironmentVariableCollection
    {
        return new EnvironmentVariableCollection(array_merge($this->environmentVariables, [$environmentVariable]));
    }

    public function set(int $index, EnvironmentVariable $environmentVariable): EnvironmentVariableCollection
    {
        $newCollection = $this->environmentVariables;
        $newCollection[$index] = $environmentVariable;

        return new EnvironmentVariableCollection($newCollection);
    }
}
