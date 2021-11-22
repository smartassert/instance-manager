<?php

namespace App\Model;

class EnvironmentVariable
{
    public function __construct(
        private string $key,
        private string $value,
    ) {
    }

    public function __toString(): string
    {
        if ('' === $this->key) {
            return '';
        }

        return $this->key . '="' . str_replace('"', '\\"', $this->value) . '"';
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
