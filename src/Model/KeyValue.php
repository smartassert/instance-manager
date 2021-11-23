<?php

namespace App\Model;

class KeyValue
{
    public function __construct(
        private string $key,
        private string $value,
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
