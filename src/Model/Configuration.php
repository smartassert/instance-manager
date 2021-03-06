<?php

declare(strict_types=1);

namespace App\Model;

class Configuration
{
    /**
     * @var array<int|string, scalar>
     */
    private array $data = [];

    /**
     * @param array<int|string, mixed> $data
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

    public function getInt(string $key): ?int
    {
        $value = $this->get($key);

        return is_int($value) || is_numeric($value) ? (int) $value : null;
    }

    public function getString(string $key): ?string
    {
        $value = $this->get($key);

        return is_string($value) ? $value : null;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getAll(): array
    {
        return $this->data;
    }
}
