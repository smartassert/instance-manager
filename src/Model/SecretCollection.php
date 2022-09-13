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

    /**
     * @param string[] $prefixes
     */
    public function filterByKeyPrefixes(array $prefixes): SecretCollection
    {
        $collection = [];

        foreach ($this->secrets as $secret) {
            if ($secret instanceof Secret) {
                foreach ($prefixes as $prefix) {
                    if (is_string($prefix) && str_starts_with($secret->getKey(), $prefix)) {
                        $collection[] = $secret;
                    }
                }
            }
        }

        return new SecretCollection($collection);
    }
}
