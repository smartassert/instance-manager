<?php

declare(strict_types=1);

namespace App\Model;

/**
 * @implements \IteratorAggregate<int, Secret>
 */
class SecretCollection implements \IteratorAggregate
{
    /**
     * @param array<int, Secret> $secrets
     */
    public function __construct(
        private readonly array $secrets = [],
    ) {
    }

    /**
     * @return \Traversable<int, Secret>
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->secrets);
    }
}
