<?php

declare(strict_types=1);

namespace App\Model;

class FooConfiguration
{
    /**
     * @var array<string, scalar>
     */
    private array $data = [];

    /**
     * @param array<mixed> $data
     */
    public function __construct(array $data)
    {
        foreach ($data as $key => $value) {
            if (is_scalar($value)) {
                $this->data[$key] = $value;
            }
        }
    }

    public function get(string $key, bool|float|int|string|null $default = null): bool|float|int|string|null
    {
        return $this->data[$key] ?? $default;
    }

    public function getInt(string $key, ?int $default = null): ?int
    {
        $value = $this->get($key);

        return is_int($value) || is_numeric($value) ? (int) $value : $default;
    }

    public function getString(string $key, ?string $default = null): ?string
    {
        $value = $this->get($key);

        return is_string($value) ? $value : $default;
    }
}
