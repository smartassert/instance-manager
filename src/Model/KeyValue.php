<?php

declare(strict_types=1);

namespace App\Model;

class KeyValue
{
    public function __construct(
        private readonly string $key,
        private readonly string $value,
    ) {
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
